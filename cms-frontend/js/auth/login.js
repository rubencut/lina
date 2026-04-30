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

        const res = await fetch(`${API}${url}`, {
            method: "POST",
            cache: "no-store",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Cache-Control": "no-store"
            },
            body: JSON.stringify(payload)
        });

        const data = await readJson(res);

        if (isLogin) {
            saveSession(data);
            window.location.replace("dashboard.html");
        } else {
            toggle.click();
        }
    } catch (err) {
        Swal.fire("Authentication Error", apiError(err, "We could not complete your request. Please review your details and try again."), "error");
    } finally {
        button.disabled = false;
    }
};
