async function loadClassrooms() {
    const res = await fetch(`${API}/classrooms?per_page=100`, {
        headers: authHeaders()
    });

    const data = await readJson(res);
    const rows = data.data || [];

    let html = `
    <tr>
        <th>ID</th><th>Name</th><th>Teacher</th><th>Status</th><th>Description</th>
    </tr>`;

    if (rows.length === 0) {
        html += `<tr><td colspan="5">No classrooms found.</td></tr>`;
    }

    rows.forEach(classroom => {
        html += `
        <tr>
            <td>${classroom.id}</td>
            <td>${classroom.name}</td>
            <td>${classroom.teacher?.name ?? "-"}</td>
            <td>${displayStatus(classroom.status)}</td>
            <td>${classroom.description ?? "-"}</td>
        </tr>`;
    });

    document.getElementById("classroomsTable").innerHTML = html;
}

async function addClassroom() {
    const { value: form } = await Swal.fire({
        title: "Add Classroom",
        html: `
        <input id="name" class="swal2-input" placeholder="Classroom name">
        <input id="teacher_id" class="swal2-input" placeholder="Teacher ID">
        <input id="description" class="swal2-input" placeholder="Description">
        `,
        showCancelButton: true,
        preConfirm: () => {
            const name = document.getElementById("name").value.trim();
            const teacher_id = document.getElementById("teacher_id").value;
            const description = document.getElementById("description").value.trim();

            if (!name) return showFormError("Classroom name is required");

            return { name, teacher_id: teacher_id || null, description, status: "active" };
        }
    });

    if (!form) return;

    try {
        const res = await fetch(`${API}/classrooms`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(form)
        });

        await readJson(res);
        Swal.fire("Classroom Created", "The classroom was created.", "success");
        loadClassrooms();
    } catch (err) {
        Swal.fire("Create Failed", apiError(err, "The classroom could not be created."), "error");
    }
}
