let classroomData = [];

function currentUser() {
    try {
        return JSON.parse(localStorage.getItem("user") || "{}");
    } catch {
        return {};
    }
}

function canManageClassrooms() {
    return ["super_admin", "staff_teacher_supervisor"].includes(currentUser().role);
}

async function loadClassrooms() {
    const addButton = document.getElementById("addClassroomButton");
    if (addButton) addButton.style.display = canManageClassrooms() ? "" : "none";
    if (addButton?.parentElement) addButton.parentElement.style.display = canManageClassrooms() ? "" : "none";

    const res = await fetch(`${API}/classrooms?per_page=100`, {
        headers: authHeaders()
    });

    const data = await readJson(res);
    classroomData = data.data || [];
    renderClassrooms();
}

function renderClassrooms() {
    let html = `
    <tr>
        <th>ID</th><th>Name</th><th>Teacher</th><th>Status</th><th>Description</th><th>Action</th>
    </tr>`;

    if (!classroomData.length) {
        html += `<tr><td colspan="6">No classrooms found.</td></tr>`;
    }

    classroomData.forEach(classroom => {
        html += `
        <tr>
            <td>${classroom.id}</td>
            <td>${classroom.name}</td>
            <td>${classroom.teacher?.name ?? "-"}</td>
            <td>${statusBadge(classroom.status)}</td>
            <td>${classroom.description ?? "-"}</td>
            <td><button class="action-btn edit" onclick="viewClassroom(${classroom.id})">Open</button></td>
        </tr>`;
    });

    document.getElementById("classroomsTable").innerHTML = html;
}

async function addClassroom() {
    const isAdmin = currentUser().role === "super_admin";
    const teacherInput = isAdmin ? '<input id="teacher_id" class="swal2-input" placeholder="Teacher ID">' : "";

    const { value: form } = await Swal.fire({
        title: "Add Classroom",
        html: `
        <input id="name" class="swal2-input" placeholder="Classroom name">
        ${teacherInput}
        <input id="description" class="swal2-input" placeholder="Description">
        `,
        showCancelButton: true,
        preConfirm: () => {
            const name = document.getElementById("name").value.trim();
            const teacher = document.getElementById("teacher_id");
            const description = document.getElementById("description").value.trim();

            if (!name) return showFormError("Classroom name is required");

            return {
                name,
                teacher_id: teacher?.value || null,
                description,
                status: "active"
            };
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

async function viewClassroom(id) {
    try {
        const today = new Date().toISOString().slice(0, 10);
        const [classroom, students, attendance] = await Promise.all([
            apiGet(`classrooms/${id}`),
            apiGet(`classrooms/${id}/students`),
            apiGet(`classrooms/${id}/attendance?draft=1&date=${today}`)
        ]);

        const records = attendance.data || [];
        const recordsByStudent = new Map(records.map(record => [Number(record.user_id), record]));
        const canRecord = canManageClassrooms();
        const rows = students.length
            ? students.map(student => studentRow(id, student, recordsByStudent.get(Number(student.id)), canRecord)).join("")
            : `<tr><td colspan="${canRecord ? 6 : 5}">No students assigned.</td></tr>`;

        await Swal.fire({
            title: classroom.name,
            width: 1050,
            html: `
                <p class="modal-muted">${classroom.description ?? ""}</p>
                ${canRecord ? classroomActions(id) : ""}
                <table class="modal-table">
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>QR Code</th>
                        <th>Status</th>
                        <th>Time In</th>
                        ${canRecord ? "<th>Mark</th>" : ""}
                    </tr>
                    ${rows}
                </table>
            `,
            confirmButtonText: "Close"
        });
    } catch (err) {
        Swal.fire("Load Failed", apiError(err, "The classroom could not be loaded."), "error");
    }
}

function classroomActions(classroomId) {
    return `
    <div class="modal-actions">
        <button class="primary" onclick="Swal.close(); addStudents(${classroomId})">Add Students</button>
        <button class="primary" onclick="importAttendanceCsv(${classroomId})">Import CSV</button>
        <button class="primary" onclick="exportClassroomPdf(${classroomId})">Export PDF</button>
        <button class="primary" onclick="submitTodayAttendance(${classroomId})">Submit Today</button>
    </div>`;
}

function studentRow(classroomId, student, record, canRecord) {
    return `
    <tr data-student-id="${student.id}">
        <td>${student.name}</td>
        <td>${student.email}</td>
        <td>${studentQrCell(classroomId, student)}</td>
        <td data-attendance-status>${record ? statusBadge(record.status) : "-"}</td>
        <td data-attendance-time>${record?.time_in ?? "-"}</td>
        ${canRecord ? `<td><div class="attendance-buttons">${attendanceButtons(classroomId, student.id, record)}</div></td>` : ""}
    </tr>`;
}

function studentQrCell(classroomId, student) {
    if (student.qr_image) {
        return `
            <button class="qr-thumb-button" onclick="viewQr(${student.id})" title="Open ${student.name} QR code">
                <img src="${student.qr_image}" alt="QR code for ${student.name}">
            </button>
        `;
    }

    return `<button class="action-btn edit" onclick="generateClassroomQr(${classroomId}, ${student.id})">Generate QR</button>`;
}

async function generateClassroomQr(classroomId, studentId) {
    await generateQr(studentId);
    viewClassroom(classroomId);
}

function attendanceButtons(classroomId, studentId, record) {
    const recordId = record?.id || null;

    return ["Present", "Absent", "Late", "Excused"].map(status => `
        <button class="attendance-mark ${record?.status === status ? "active" : ""}"
            onclick="markStudentAttendance(${classroomId}, ${studentId}, '${status}', ${recordId})">
            ${status}
        </button>
    `).join("");
}

async function markStudentAttendance(classroomId, studentId, status, recordId = null) {
    try {
        const res = await fetch(recordId ? `${API}/attendance/${recordId}` : `${API}/attendance`, {
            method: recordId ? "PUT" : "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(attendancePayload(classroomId, studentId, status))
        });

        const record = await readJson(res);
        updateAttendanceRow(classroomId, studentId, record);
        loadDashboard();
    } catch (err) {
        Swal.fire("Save Failed", apiError(err, "Attendance could not be saved."), "error");
    }
}

function updateAttendanceRow(classroomId, studentId, record) {
    const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
    if (!row) return;

    row.querySelector("[data-attendance-status]").innerHTML = statusBadge(record.status);
    row.querySelector("[data-attendance-time]").textContent = record.time_in ?? "-";
    row.querySelector(".attendance-buttons").innerHTML = attendanceButtons(classroomId, studentId, record);
}

function attendancePayload(classroomId, studentId, status) {
    const now = new Date().toTimeString().slice(0, 5);

    return {
        user_id: studentId,
        classroom_id: classroomId,
        date: new Date().toISOString().slice(0, 10),
        status,
        time_in: now
    };
}

async function submitTodayAttendance(classroomId) {
    try {
        const res = await fetch(`${API}/classrooms/${classroomId}/attendance/submit`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ date: new Date().toISOString().slice(0, 10) })
        });

        const data = await readJson(res);
        loadDashboard();
        if (typeof loadReports === "function") loadReports();
        await Swal.fire("Attendance Submitted", `${data.message} New attendance is ready.`, "success");
        viewClassroom(classroomId);
    } catch (err) {
        Swal.fire("Submit Failed", apiError(err, "Attendance could not be submitted."), "error");
    }
}

function exportClassroomPdf(classroomId) {
    const today = new Date().toISOString().slice(0, 10);
    downloadFile(
        `reports/export?report_type=classroom&format=PDF&classroom_id=${classroomId}&date_from=${today}&date_to=${today}`,
        `classroom-attendance-${classroomId}-${today}.pdf`
    );
}

async function importAttendanceCsv(classroomId) {
    const { value: file } = await Swal.fire({
        title: "Import Attendance CSV",
        input: "file",
        inputAttributes: {
            accept: ".csv,.txt,.xlsx,.xls"
        },
        showCancelButton: true,
        confirmButtonText: "Import"
    });

    if (!file) return;

    const form = new FormData();
    form.append("file", file);
    form.append("type", "attendance");

    try {
        const res = await fetch(`${API}/imports`, {
            method: "POST",
            headers: authHeaders(),
            body: form
        });

        await readJson(res);
        Swal.fire("Import Complete", "Attendance was imported.", "success");
        viewClassroom(classroomId);
    } catch (err) {
        Swal.fire("Import Failed", apiError(err, "Attendance could not be imported."), "error");
    }
}

async function addStudents(classroomId) {
    const { value } = await Swal.fire({
        title: "Add Students",
        input: "text",
        inputPlaceholder: "Student IDs, comma separated",
        showCancelButton: true,
        preConfirm: ids => {
            const user_ids = ids.split(",").map(id => Number(id.trim())).filter(Boolean);
            if (!user_ids.length) return showFormError("Enter at least one student ID");
            return user_ids;
        }
    });

    if (!value) return;

    try {
        const res = await fetch(`${API}/classrooms/${classroomId}/assign-users`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ user_ids: value })
        });

        await readJson(res);
        viewClassroom(classroomId);
    } catch (err) {
        Swal.fire("Assign Failed", apiError(err, "Students could not be added."), "error");
    }
}

async function apiGet(path) {
    const res = await fetch(`${API}/${path}`, { headers: authHeaders() });
    return readJson(res);
}
