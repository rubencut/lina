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
            <td>${user.name}</td>
            <td>${user.email}</td>
            <td>${user.phone ?? "-"}</td>
            <td>${displayStatus(user.role)}</td>
            <td>${displayStatus(user.status)}</td>
        </tr>`;
    });

    document.getElementById("usersTable").innerHTML = html;
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
        const res = await fetch(`${API}/users`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(form)
        });

        await readJson(res);
        Swal.fire("User Created", "The user account was created.", "success");
        loadUsers();
    } catch (err) {
        Swal.fire("Create Failed", apiError(err, "The user account could not be created."), "error");
    }
}
