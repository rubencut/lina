async function loadUsers() {
    const res = await fetch(`${API}/users?per_page=100`, {
        headers: authHeaders()
    });

    const data = await readJson(res);
    const rows = data.data || [];

    let html = `
    <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th>
    </tr>`;

    if (rows.length === 0) {
        html += `<tr><td colspan="6">No users found.</td></tr>`;
    }

    rows.forEach(user => {
        html += `
        <tr>
            <td>${user.id}</td>
            <td>${userName(user)}</td>
            <td>${user.email}</td>
            <td>${user.phone ?? "-"}</td>
            <td>${displayStatus(user.role)}</td>
            <td>${statusBadge(user.status)}</td>
        </tr>`;
    });

    document.getElementById("usersTable").innerHTML = html;
}

function userName(user) {
    if (user.qr_image) {
        return `
        <div class="user-name-qr">
            <img src="${user.qr_image}" alt="QR code for ${user.name}">
            <span>${user.name}</span>
        </div>`;
    }

    return `
    <div class="user-name-qr">
        <button class="action-btn edit" onclick="generateUserQr(${user.id})">Generate QR</button>
        <span>${user.name}</span>
    </div>`;
}

async function generateUserQr(userId) {
    try {
        const res = await fetch(`${API}/qr/generate/${userId}`, {
            method: "POST",
            headers: authHeaders()
        });

        await readJson(res);
        loadUsers();
    } catch (err) {
        Swal.fire("QR Failed", apiError(err, "The QR code could not be generated."), "error");
    }
}

async function addUser() {
    const { value: form } = await Swal.fire({
        title: "Add User",
        html: `
        <input id="name" class="swal2-input" placeholder="Full name">
        <input id="email" class="swal2-input" placeholder="Email address">
        <input id="password" class="swal2-input" placeholder="Password">
        <input id="phone" class="swal2-input" placeholder="Phone">
        <select id="role" class="swal2-input">
            <option value="super_admin">Super Admin</option>
            <option value="staff_teacher_supervisor">Staff / Teacher / Supervisor</option>
            <option value="student_employee_participant">Student / Employee / Participant</option>
        </select>
        `,
        showCancelButton: true,
        preConfirm: () => {
            const name = document.getElementById("name").value.trim();
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value;
            const phone = document.getElementById("phone").value.trim();
            const role = document.getElementById("role").value;

            if (!name) return showFormError("Name is required");
            if (!email || !validEmail(email)) return showFormError("A valid email is required");
            if (!password || password.length < 8) return showFormError("Password must be at least 8 characters");

            return { name, email, password, phone, role, status: "active" };
        }
    });

    if (!form) return;

    try {
        const user = await postJson("/users", form, authHeaders());
        let message = "The user account was created.";

        if (user.verification_required) {
            const verified = await promptVerificationCode(user.email);
            message = verified
                ? "The user account was created and verified."
                : "The user account was created. It still needs the emailed verification code before sign-in.";
        }

        Swal.fire("User Created", message, "success");
        loadUsers();
    } catch (err) {
        Swal.fire("Create Failed", apiError(err, "The user account could not be created."), "error");
    }
}
