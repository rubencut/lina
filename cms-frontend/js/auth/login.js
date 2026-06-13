let isLogin = true;

if (localStorage.getItem("token") && !isSessionExpired()) {
    window.location.replace("dashboard.html");
}

const title = document.getElementById("title");
const subtitle = document.getElementById("subtitle");
const nameInput = document.getElementById("name");
const email = document.getElementById("email");
const password = document.getElementById("password");
const button = document.getElementById("btn");
const toggle = document.getElementById("toggle");
const authForm = document.getElementById("authForm");

toggle.onclick = () => {
    isLogin = !isLogin;

    title.innerText = isLogin ? "Classroom Record System" : "Student Registration";
    subtitle.innerText = isLogin
        ? "Sign in to manage attendance, classrooms, members, reports, and QR records."
        : "Create a student account for attendance history and reports.";
    button.innerText = isLogin ? "Sign In" : "Create Student Account";
    toggle.innerText = isLogin ? "Create a student account" : "Return to sign in";
    nameInput.style.display = isLogin ? "none" : "block";
};

authForm.onsubmit = async (event) => {
    event.preventDefault();

    try {
        button.disabled = true;

        const url = isLogin ? "/login" : "/register";
        const payload = isLogin ? {
            email: email.value.trim(),
            password: password.value
        } : {
            name: nameInput.value.trim(),
            email: email.value.trim(),
            password: password.value
        };

        const data = await postAuth(url, payload);

        if (isLogin) {
            saveSession(data);
            window.location.replace("dashboard.html");
        } else {
            if (data.verification_required) {
                await promptVerificationCode(payload.email);
            }

            toggle.click();
        }
    } catch (err) {
        if (isLogin && err.verification_required) {
            const verified = await promptVerificationCode(email.value.trim());

            if (verified) {
                try {
                    const data = await postAuth("/login", {
                        email: email.value.trim(),
                        password: password.value
                    });

                    saveSession(data);
                    window.location.replace("dashboard.html");
                    return;
                } catch (retryErr) {
                    Swal.fire("Authentication Error", apiError(retryErr, "We could not sign you in after verification."), "error");
                    return;
                }
            }

            return;
        }

        Swal.fire("Authentication Error", apiError(err, "We could not complete your request. Please review your details and try again."), "error");
    } finally {
        button.disabled = false;
    }
};

async function postAuth(path, payload) {
    return postJson(path, payload);
}
