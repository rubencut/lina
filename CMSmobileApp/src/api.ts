import type {
  AttendancePage,
  LoginResponse,
  MarkQrResponse,
  VerifyCodeResponse,
} from "@/types";

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly details?: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function normalizeApiUrl(value: string) {
  return value.trim().replace(/\/+$/, "");
}

async function parseResponse(response: Response) {
  const text = await response.text();

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { message: text };
  }
}

async function request<T>(
  apiUrl: string,
  path: string,
  options: RequestInit = {},
) {
  const response = await fetch(`${normalizeApiUrl(apiUrl)}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...options.headers,
    },
  });
  const body = await parseResponse(response);

  if (!response.ok) {
    const message =
      body && typeof body === "object" && "message" in body
        ? String(body.message)
        : `Request failed with status ${response.status}`;

    throw new ApiError(message, response.status, body);
  }

  return body as T;
}

export function login(apiUrl: string, email: string, password: string) {
  return request<LoginResponse>(apiUrl, "/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export function verifyCode(apiUrl: string, email: string, code: string) {
  return request<VerifyCodeResponse>(apiUrl, "/verify-code", {
    method: "POST",
    body: JSON.stringify({ email, code }),
  });
}

export function markPresentByQr(apiUrl: string, token: string, qrCode: string) {
  return request<MarkQrResponse>(apiUrl, "/attendance/mark-by-qr", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ qr_code: qrCode }),
  });
}

export function fetchPresentAttendance(
  apiUrl: string,
  token: string,
  date: string,
) {
  const params = new URLSearchParams({
    date,
    status: "Present",
    per_page: "100",
  });

  return request<AttendancePage>(apiUrl, `/attendance?${params.toString()}`, {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });
}

export function logout(apiUrl: string, token: string) {
  return request<{ message: string }>(apiUrl, "/logout", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });
}
