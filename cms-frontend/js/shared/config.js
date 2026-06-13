const API = "https://linayawa.yaxdedal.site/api";
const SESSION_KEY = "sessionExpiresAt";
const token = localStorage.getItem("token");

function authHeaders() {
    return {
        "Authorization": `Bearer ${localStorage.getItem("token")}`,
        "Accept": "application/json"
    };
}

function saveSession(data) {
    const expiresAt = new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString();

    localStorage.setItem("token", data.token);
    localStorage.setItem("user", JSON.stringify(data.user));
    localStorage.setItem(SESSION_KEY, expiresAt);
}

function clearSession() {
    localStorage.clear();
}

function isSessionExpired() {
    const expiresAt = localStorage.getItem(SESSION_KEY);

    return !expiresAt || new Date(expiresAt).getTime() <= Date.now();
}

async function readJson(res) {
    const text = await res.text();
    let data = {};

    try {
        data = text ? JSON.parse(text) : {};
    } catch {
        data = { message: "The server returned an unexpected response." };
    }

    if (!res.ok) throw data;

    return data;
}

function apiError(err, fallback) {
    if (err.errors) {
        return Object.values(err.errors).flat().join("\n");
    }

    return err.message || fallback;
}

function showFormError(message) {
    Swal.showValidationMessage(message);
    return false;
}

function validEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function displayStatus(status) {
    return String(status ?? "-")
        .replaceAll("_", " ")
        .replace(/\b\w/g, letter => letter.toUpperCase());
}

function formatDate(value) {
    if (!value) return "-";

    return String(value).slice(0, 10);
}

function downloadFile(path, filename = "export.csv") {
    fetch(`${API}/${path}`, { headers: authHeaders() })
        .then(async res => {
            if (!res.ok) throw await readJson(res);
            return res.blob();
        })
        .then(blob => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
        })
        .catch(err => {
            Swal.fire("Download Failed", apiError(err, "The file could not be downloaded."), "error");
        });
}

async function postJson(path, payload, headers = {}) {
    const res = await fetch(`${API}${path}`, {
        method: "POST",
        cache: "no-store",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Cache-Control": "no-store",
            ...headers
        },
        body: JSON.stringify(payload)
    });

    return readJson(res);
}

async function promptVerificationCode(emailAddress) {
    const { value: verified } = await Swal.fire({
        title: "Verification Code",
        text: `Enter the code sent to ${emailAddress}.`,
        input: "text",
        inputAttributes: {
            maxlength: 6,
            inputmode: "numeric"
        },
        showCancelButton: true,
        confirmButtonText: "Verify",
        preConfirm: async (code) => {
            const value = String(code || "").trim();

            if (!/^\d{6}$/.test(value)) {
                return showFormError("Enter the 6-digit verification code");
            }

            try {
                await postJson("/verify-code", {
                    email: emailAddress,
                    code: value
                });

                return true;
            } catch (err) {
                return showFormError(apiError(err, "The verification code could not be verified."));
            }
        }
    });

    return Boolean(verified);
}
