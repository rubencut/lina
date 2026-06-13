import { Ionicons } from "@expo/vector-icons";
import {
  BarcodeScanningResult,
  CameraView,
  useCameraPermissions,
} from "expo-camera";
import { StatusBar } from "expo-status-bar";
import { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { SafeAreaProvider, SafeAreaView } from "react-native-safe-area-context";

import {
  ApiError,
  fetchPresentAttendance,
  login,
  logout,
  markPresentByQr,
  normalizeApiUrl,
  verifyCode,
} from "@/api";
import { clearSession, loadSession, saveApiUrl, saveSession } from "@/session-storage";
import type { AttendanceRecord, MarkQrResponse, User } from "@/types";

const DEFAULT_API_URL =
  process.env.EXPO_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";

export default function App() {
  const [booting, setBooting] = useState(true);
  const [apiUrl, setApiUrl] = useState(DEFAULT_API_URL);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [verificationEmail, setVerificationEmail] = useState<string | null>(null);
  const [verificationCode, setVerificationCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [cameraOpen, setCameraOpen] = useState(false);
  const [scanLocked, setScanLocked] = useState(false);
  const [scanResult, setScanResult] = useState<MarkQrResponse | null>(null);
  const [presentRecords, setPresentRecords] = useState<AttendanceRecord[]>([]);
  const [attendanceLoading, setAttendanceLoading] = useState(false);
  const [attendanceDate] = useState(todayDate());
  const [permission, requestPermission] = useCameraPermissions();

  const isTeacher = user?.role === "staff_teacher_supervisor";
  const normalizedApiUrl = useMemo(() => normalizeApiUrl(apiUrl), [apiUrl]);

  useEffect(() => {
    loadSession()
      .then((session) => {
        if (session.apiUrl) {
          setApiUrl(session.apiUrl);
        }
        if (session.token && session.user) {
          setToken(session.token);
          setUser(session.user);
        }
      })
      .catch(() => setMessage("Stored session could not be restored."))
      .finally(() => setBooting(false));
  }, []);

  useEffect(() => {
    if (token && isTeacher) {
      loadPresentAttendance(true);
    } else {
      setPresentRecords([]);
    }
  }, [token, isTeacher, normalizedApiUrl]);

  async function handleLogin() {
    setLoading(true);
    setMessage(null);

    try {
      const response = await login(normalizedApiUrl, email.trim(), password);
      await saveSession(response.token, response.user, normalizedApiUrl);
      setToken(response.token);
      setUser(response.user);
      setPassword("");
      setVerificationEmail(null);
      setVerificationCode("");
      setScanResult(null);
      setCameraOpen(false);
    } catch (error) {
      const pendingEmail = verificationEmailFromError(error);

      if (pendingEmail) {
        setVerificationEmail(pendingEmail);
        setVerificationCode("");
      }

      setMessage(messageFromError(error));
    } finally {
      setLoading(false);
    }
  }

  async function handleVerifyCode() {
    if (!verificationEmail) {
      return;
    }

    setLoading(true);
    setMessage(null);

    try {
      await verifyCode(normalizedApiUrl, verificationEmail, verificationCode.trim());
      const response = await login(normalizedApiUrl, verificationEmail, password);
      await saveSession(response.token, response.user, normalizedApiUrl);
      setToken(response.token);
      setUser(response.user);
      setEmail(verificationEmail);
      setPassword("");
      setVerificationEmail(null);
      setVerificationCode("");
      setScanResult(null);
      setCameraOpen(false);
    } catch (error) {
      setMessage(messageFromError(error));
    } finally {
      setLoading(false);
    }
  }

  async function handleLogout() {
    const activeToken = token;
    setLoading(true);
    setMessage(null);

    try {
      if (activeToken) {
        await logout(normalizedApiUrl, activeToken);
      }
    } catch {
      // Local sign-out should still succeed if the token has expired.
    } finally {
      await clearSession();
      setToken(null);
      setUser(null);
      setCameraOpen(false);
      setScanResult(null);
      setLoading(false);
    }
  }

  async function handleSaveApiUrl() {
    const nextUrl = normalizeApiUrl(apiUrl);
    setApiUrl(nextUrl);
    await saveApiUrl(nextUrl);
    setMessage("API URL saved.");
  }

  async function handleScan(result: BarcodeScanningResult) {
    if (!token || scanLocked) {
      return;
    }

    setScanLocked(true);
    setMessage("Marking attendance...");

    try {
      const response = await markPresentByQr(normalizedApiUrl, token, result.data);
      setScanResult(response);
      setMessage(response.message);
      setCameraOpen(false);
      await loadPresentAttendance(true);
    } catch (error) {
      setMessage(messageFromError(error));
    } finally {
      setScanLocked(false);
    }
  }

  async function loadPresentAttendance(silent = false) {
    if (!token) {
      return;
    }

    setAttendanceLoading(true);

    try {
      const response = await fetchPresentAttendance(
        normalizedApiUrl,
        token,
        attendanceDate,
      );
      setPresentRecords(response.data || []);
      if (!silent) {
        setMessage("Attendance table refreshed.");
      }
    } catch (error) {
      setMessage(messageFromError(error));
    } finally {
      setAttendanceLoading(false);
    }
  }

  if (booting) {
    return (
      <AppShell>
        <CenteredPanel>
          <ActivityIndicator size="large" color="#0f766e" />
          <Text style={styles.mutedText} selectable>
            Loading session
          </Text>
        </CenteredPanel>
      </AppShell>
    );
  }

  if (!token || !user) {
    return (
      <AppShell>
        <KeyboardAvoidingView behavior="padding" style={{ flex: 1 }}>
          <ScrollView
            contentInsetAdjustmentBehavior="automatic"
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={styles.page}
          >
            <View style={styles.header}>
              <View style={styles.brandMark}>
                <Ionicons name="qr-code" size={28} color="#f8fafc" />
              </View>
              <Text style={styles.title} selectable>
                CMS Mobile
              </Text>
              <Text style={styles.subtitle} selectable>
                Teacher attendance scanner
              </Text>
            </View>

            <View style={styles.panel}>
              <FieldLabel>API URL</FieldLabel>
              <TextInput
                value={apiUrl}
                onChangeText={setApiUrl}
                autoCapitalize="none"
                autoCorrect={false}
                inputMode="url"
                placeholder="http://192.168.1.10:8000/api"
                placeholderTextColor="#94a3b8"
                style={styles.input}
              />
              <Pressable style={styles.secondaryButton} onPress={handleSaveApiUrl}>
                <Ionicons name="server-outline" size={18} color="#0f172a" />
                <Text style={styles.secondaryButtonText}>Save API URL</Text>
              </Pressable>
            </View>

            {verificationEmail ? (
              <View style={styles.panel}>
                <FieldLabel>Verification code</FieldLabel>
                <TextInput
                  value={verificationCode}
                  onChangeText={setVerificationCode}
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="number-pad"
                  maxLength={6}
                  placeholder="6-digit code"
                  placeholderTextColor="#94a3b8"
                  style={styles.input}
                />
                <Text style={styles.bodyText} selectable>
                  Code sent to {verificationEmail}
                </Text>

                <Pressable
                  disabled={loading}
                  onPress={handleVerifyCode}
                  style={[styles.primaryButton, loading && styles.disabledButton]}
                >
                  {loading ? (
                    <ActivityIndicator color="#f8fafc" />
                  ) : (
                    <Ionicons name="shield-checkmark-outline" size={20} color="#f8fafc" />
                  )}
                  <Text style={styles.primaryButtonText}>Verify code</Text>
                </Pressable>

                <Pressable
                  style={styles.secondaryButton}
                  onPress={() => {
                    setVerificationEmail(null);
                    setVerificationCode("");
                    setMessage(null);
                  }}
                >
                  <Ionicons name="arrow-back" size={18} color="#0f172a" />
                  <Text style={styles.secondaryButtonText}>Edit sign in</Text>
                </Pressable>
              </View>
            ) : (
              <View style={styles.panel}>
                <FieldLabel>Email</FieldLabel>
                <TextInput
                  value={email}
                  onChangeText={setEmail}
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="email-address"
                  placeholder="teacher@classroom.local"
                  placeholderTextColor="#94a3b8"
                  style={styles.input}
                />

                <FieldLabel>Password</FieldLabel>
                <TextInput
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry
                  placeholder="Password"
                  placeholderTextColor="#94a3b8"
                  style={styles.input}
                />

                <Pressable
                  disabled={loading}
                  onPress={handleLogin}
                  style={[styles.primaryButton, loading && styles.disabledButton]}
                >
                  {loading ? (
                    <ActivityIndicator color="#f8fafc" />
                  ) : (
                    <Ionicons name="log-in-outline" size={20} color="#f8fafc" />
                  )}
                  <Text style={styles.primaryButtonText}>Sign in</Text>
                </Pressable>
              </View>
            )}

            <StatusMessage message={message} />
          </ScrollView>
        </KeyboardAvoidingView>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <ScrollView
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={styles.page}
      >
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.eyebrow} selectable>
              Signed in
            </Text>
            <Text style={styles.titleSmall} selectable>
              {user.name}
            </Text>
          </View>
          <Pressable
            disabled={loading}
            onPress={handleLogout}
            style={styles.iconButton}
          >
            <Ionicons name="log-out-outline" size={21} color="#0f172a" />
          </Pressable>
        </View>

        {!isTeacher ? (
          <View style={styles.panel}>
            <View style={styles.lockIcon}>
              <Ionicons name="lock-closed" size={26} color="#92400e" />
            </View>
            <Text style={styles.panelTitle} selectable>
              Scanner unavailable
            </Text>
            <Text style={styles.bodyText} selectable>
              QR attendance scanning is limited to teacher accounts.
            </Text>
          </View>
        ) : (
          <>
            <View style={styles.panel}>
              <Text style={styles.panelTitle} selectable>
                QR attendance
              </Text>
              <Text style={styles.bodyText} selectable>
                Scan a student QR code to mark today&apos;s attendance as Present.
              </Text>

              {!permission?.granted ? (
                <Pressable style={styles.primaryButton} onPress={requestPermission}>
                  <Ionicons name="camera-outline" size={20} color="#f8fafc" />
                  <Text style={styles.primaryButtonText}>Allow camera</Text>
                </Pressable>
              ) : (
                <Pressable
                  style={styles.primaryButton}
                  onPress={() => {
                    setCameraOpen(true);
                    setScanResult(null);
                    setMessage(null);
                  }}
                >
                  <Ionicons name="scan-outline" size={20} color="#f8fafc" />
                  <Text style={styles.primaryButtonText}>Start scan</Text>
                </Pressable>
              )}
            </View>

            {cameraOpen && permission?.granted ? (
              <View style={styles.cameraPanel}>
                <CameraView
                  style={styles.camera}
                  facing="back"
                  onBarcodeScanned={scanLocked ? undefined : handleScan}
                  barcodeScannerSettings={{ barcodeTypes: ["qr"] }}
                >
                  <View style={styles.scanFrame} />
                </CameraView>
                <View style={styles.cameraActions}>
                  <Text style={styles.cameraText} selectable>
                    {scanLocked ? "Processing scan" : "Align the QR code inside the frame"}
                  </Text>
                  <Pressable
                    style={styles.secondaryButton}
                    onPress={() => setCameraOpen(false)}
                  >
                    <Ionicons name="close" size={18} color="#0f172a" />
                    <Text style={styles.secondaryButtonText}>Cancel</Text>
                  </Pressable>
                </View>
              </View>
            ) : null}

            {scanResult ? (
              <View style={styles.successPanel}>
                <Ionicons name="checkmark-circle" size={30} color="#15803d" />
                <View style={{ flex: 1 }}>
                  <Text style={styles.successTitle} selectable>
                    {scanResult.attendance.user?.name ?? "Student"} marked Present
                  </Text>
                  <Text style={styles.bodyText} selectable>
                    {scanResult.already_recorded
                      ? "Attendance was already recorded for today."
                      : "Attendance was recorded successfully."}
                  </Text>
                </View>
              </View>
            ) : null}

            <View style={styles.panel}>
              <View style={styles.tableHeader}>
                <View>
                  <Text style={styles.panelTitle} selectable>
                    Present today
                  </Text>
                  <Text style={styles.bodyText} selectable>
                    {attendanceDate} · {presentRecords.length} student
                    {presentRecords.length === 1 ? "" : "s"}
                  </Text>
                </View>
                <Pressable
                  disabled={attendanceLoading}
                  onPress={() => loadPresentAttendance(false)}
                  style={styles.refreshButton}
                >
                  {attendanceLoading ? (
                    <ActivityIndicator color="#0f172a" />
                  ) : (
                    <Ionicons name="refresh" size={19} color="#0f172a" />
                  )}
                </Pressable>
              </View>

              <View style={styles.attendanceTable}>
                <View style={[styles.attendanceRow, styles.attendanceHeadRow]}>
                  <Text style={styles.attendanceHeadText} selectable>
                    Student
                  </Text>
                  <Text style={styles.attendanceHeadText} selectable>
                    Time
                  </Text>
                </View>
                {presentRecords.length ? (
                  presentRecords.map((record) => (
                    <View key={record.id} style={styles.attendanceRow}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.attendanceName} selectable>
                          {record.user?.name ?? `Student #${record.user_id}`}
                        </Text>
                        <Text style={styles.attendanceClassroom} selectable>
                          {record.classroom?.name ?? "No classroom"}
                        </Text>
                      </View>
                      <Text style={styles.attendanceTime} selectable>
                        {formatTime(record.time_in)}
                      </Text>
                    </View>
                  ))
                ) : (
                  <View style={styles.emptyAttendance}>
                    <Ionicons name="clipboard-outline" size={24} color="#64748b" />
                    <Text style={styles.bodyText} selectable>
                      No students are marked present yet.
                    </Text>
                  </View>
                )}
              </View>
            </View>
          </>
        )}

        <StatusMessage message={message} />
      </ScrollView>
    </AppShell>
  );
}

function AppShell({ children }: { children: React.ReactNode }) {
  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="dark" />
        {children}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

function CenteredPanel({ children }: { children: React.ReactNode }) {
  return <View style={styles.centeredPanel}>{children}</View>;
}

function FieldLabel({ children }: { children: string }) {
  return (
    <Text style={styles.fieldLabel} selectable>
      {children}
    </Text>
  );
}

function StatusMessage({ message }: { message: string | null }) {
  if (!message) {
    return null;
  }

  return (
    <View style={styles.messageBox}>
      <Ionicons name="information-circle-outline" size={19} color="#0369a1" />
      <Text style={styles.messageText} selectable>
        {message}
      </Text>
    </View>
  );
}

function verificationEmailFromError(error: unknown) {
  if (!(error instanceof ApiError) || error.status !== 409) {
    return null;
  }

  if (!isRecord(error.details) || error.details.verification_required !== true) {
    return null;
  }

  return typeof error.details.email === "string" ? error.details.email : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function messageFromError(error: unknown) {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Something went wrong.";
}

function todayDate() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function formatTime(value: string | null) {
  if (!value) {
    return "-";
  }

  return value.slice(0, 5);
}

const styles = {
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  page: {
    flexGrow: 1,
    padding: 20,
    gap: 16,
  },
  centeredPanel: {
    flex: 1,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    gap: 12,
  },
  header: {
    gap: 8,
    paddingTop: 12,
    paddingBottom: 6,
  },
  headerRow: {
    flexDirection: "row" as const,
    alignItems: "center" as const,
    justifyContent: "space-between" as const,
    gap: 16,
  },
  brandMark: {
    width: 56,
    height: 56,
    borderRadius: 16,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    backgroundColor: "#0f766e",
  },
  title: {
    color: "#0f172a",
    fontSize: 34,
    fontWeight: "800" as const,
    letterSpacing: 0,
  },
  titleSmall: {
    color: "#0f172a",
    fontSize: 24,
    fontWeight: "800" as const,
    letterSpacing: 0,
  },
  subtitle: {
    color: "#475569",
    fontSize: 16,
  },
  eyebrow: {
    color: "#64748b",
    fontSize: 13,
    fontWeight: "700" as const,
    letterSpacing: 0,
    textTransform: "uppercase" as const,
  },
  panel: {
    gap: 12,
    padding: 16,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
  },
  panelTitle: {
    color: "#0f172a",
    fontSize: 20,
    fontWeight: "800" as const,
    letterSpacing: 0,
  },
  bodyText: {
    color: "#475569",
    fontSize: 15,
    lineHeight: 21,
  },
  mutedText: {
    color: "#64748b",
    fontSize: 15,
  },
  fieldLabel: {
    color: "#334155",
    fontSize: 13,
    fontWeight: "700" as const,
  },
  input: {
    minHeight: 48,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#f8fafc",
    color: "#0f172a",
    paddingHorizontal: 14,
    fontSize: 16,
  },
  primaryButton: {
    minHeight: 50,
    borderRadius: 8,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    flexDirection: "row" as const,
    gap: 8,
    backgroundColor: "#0f766e",
  },
  primaryButtonText: {
    color: "#f8fafc",
    fontSize: 16,
    fontWeight: "800" as const,
  },
  secondaryButton: {
    minHeight: 44,
    borderRadius: 8,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    flexDirection: "row" as const,
    gap: 8,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#ffffff",
  },
  secondaryButtonText: {
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "700" as const,
  },
  disabledButton: {
    opacity: 0.7,
  },
  iconButton: {
    width: 44,
    height: 44,
    borderRadius: 8,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#ffffff",
  },
  lockIcon: {
    width: 52,
    height: 52,
    borderRadius: 8,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    backgroundColor: "#fef3c7",
  },
  cameraPanel: {
    overflow: "hidden" as const,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#0f172a",
    backgroundColor: "#020617",
  },
  camera: {
    height: 390,
    justifyContent: "center" as const,
    alignItems: "center" as const,
  },
  scanFrame: {
    width: 230,
    height: 230,
    borderRadius: 8,
    borderWidth: 3,
    borderColor: "#f8fafc",
    backgroundColor: "transparent",
  },
  cameraActions: {
    gap: 12,
    padding: 14,
    backgroundColor: "#020617",
  },
  cameraText: {
    color: "#e2e8f0",
    textAlign: "center" as const,
    fontSize: 14,
  },
  successPanel: {
    flexDirection: "row" as const,
    alignItems: "center" as const,
    gap: 12,
    padding: 16,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#bbf7d0",
    backgroundColor: "#f0fdf4",
  },
  successTitle: {
    color: "#14532d",
    fontSize: 16,
    fontWeight: "800" as const,
  },
  tableHeader: {
    flexDirection: "row" as const,
    alignItems: "center" as const,
    justifyContent: "space-between" as const,
    gap: 12,
  },
  refreshButton: {
    width: 42,
    height: 42,
    borderRadius: 8,
    alignItems: "center" as const,
    justifyContent: "center" as const,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#ffffff",
  },
  attendanceTable: {
    overflow: "hidden" as const,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#e2e8f0",
  },
  attendanceRow: {
    minHeight: 52,
    flexDirection: "row" as const,
    alignItems: "center" as const,
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 9,
    borderBottomWidth: 1,
    borderBottomColor: "#e2e8f0",
    backgroundColor: "#ffffff",
  },
  attendanceHeadRow: {
    minHeight: 38,
    backgroundColor: "#f8fafc",
  },
  attendanceHeadText: {
    flex: 1,
    color: "#475569",
    fontSize: 12,
    fontWeight: "800" as const,
    textTransform: "uppercase" as const,
  },
  attendanceName: {
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "800" as const,
  },
  attendanceClassroom: {
    color: "#64748b",
    fontSize: 13,
  },
  attendanceTime: {
    width: 58,
    color: "#0f172a",
    fontSize: 15,
    fontVariant: ["tabular-nums" as const],
    fontWeight: "800" as const,
    textAlign: "right" as const,
  },
  emptyAttendance: {
    alignItems: "center" as const,
    justifyContent: "center" as const,
    gap: 8,
    padding: 18,
    backgroundColor: "#ffffff",
  },
  messageBox: {
    flexDirection: "row" as const,
    alignItems: "flex-start" as const,
    gap: 8,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#bae6fd",
    backgroundColor: "#f0f9ff",
  },
  messageText: {
    flex: 1,
    color: "#0c4a6e",
    fontSize: 14,
    lineHeight: 20,
  },
};
