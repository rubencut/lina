let attendanceData = [];

async function loadAttendance() {
    const res = await fetch(`${API}/attendance?per_page=100`, {
        headers: authHeaders()
    });

    const data = await readJson(res);
    attendanceData = data.data || [];
    renderAttendance();
}

function renderAttendance() {
    const search = document.getElementById("attendanceSearchInput");
    const sort = document.getElementById("attendanceSortSelect");
    const keyword = (search ? search.value : "").trim().toLowerCase();
    const sortBy = sort ? sort.value : "latest";

    let rows = attendanceData.filter(record => {
        return [
            record.user?.name,
            record.classroom?.name,
            record.date,
            record.status
        ].some(value => String(value ?? "").toLowerCase().includes(keyword));
    });

    rows.sort((a, b) => {
        if (sortBy === "date") return String(b.date ?? "").localeCompare(String(a.date ?? ""));
        if (sortBy === "user") return String(a.user?.name ?? "").localeCompare(String(b.user?.name ?? ""));
        if (sortBy === "status") return String(a.status ?? "").localeCompare(String(b.status ?? ""));

        return Number(b.id ?? 0) - Number(a.id ?? 0);
    });

    let html = `
    <tr>
        <th>ID</th><th>Date</th><th>User</th><th>Classroom</th><th>Status</th><th>Time In</th><th>Action</th>
    </tr>`;

    if (rows.length === 0) {
        html += `<tr><td colspan="7">No attendance records found.</td></tr>`;
    }

    rows.forEach(record => {
        html += `
        <tr>
            <td>${record.id}</td>
            <td>${formatDate(record.date)}</td>
            <td>${record.user?.name ?? record.user_id}</td>
            <td>${record.classroom?.name ?? "-"}</td>
            <td>${displayStatus(record.status)}</td>
            <td>${record.time_in ?? "-"}</td>
            <td>
                <button class="action-btn edit" onclick="editAttendance(${record.id})">Edit</button>
                <button class="action-btn delete" onclick="deleteAttendance(${record.id})">Delete</button>
            </td>
        </tr>`;
    });

    document.getElementById("attendanceTable").innerHTML = html;
}

document.getElementById("attendanceSearchInput")?.addEventListener("input", renderAttendance);
document.getElementById("attendanceSortSelect")?.addEventListener("change", renderAttendance);

async function addAttendance() {
    const { value: form } = await Swal.fire({
        title: "Record Attendance",
        html: `
        <input id="user_id" class="swal2-input" placeholder="User ID">
        <input id="classroom_id" class="swal2-input" placeholder="Classroom ID">
        <input id="date" class="swal2-input" type="date" value="${new Date().toISOString().slice(0, 10)}">
        <select id="status" class="swal2-input">
            <option value="Present">Present</option>
            <option value="Absent">Absent</option>
            <option value="Late">Late</option>
            <option value="Excused">Excused</option>
        </select>
        <input id="time_in" class="swal2-input" type="time">
        <input id="remarks" class="swal2-input" placeholder="Remarks">
        `,
        showCancelButton: true,
        preConfirm: () => {
            const user_id = document.getElementById("user_id").value;
            const classroom_id = document.getElementById("classroom_id").value;
            const date = document.getElementById("date").value;
            const status = document.getElementById("status").value;
            const time_in = document.getElementById("time_in").value;
            const remarks = document.getElementById("remarks").value;

            if (!user_id) return showFormError("User ID is required");
            if (!date) return showFormError("Date is required");

            return { user_id, classroom_id, date, status, time_in, remarks };
        }
    });

    if (!form) return;

    try {
        const res = await fetch(`${API}/attendance`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(form)
        });

        await readJson(res);
        Swal.fire("Attendance Saved", "The attendance record was saved.", "success");
        loadAttendance();
        loadDashboard();
    } catch (err) {
        Swal.fire("Save Failed", apiError(err, "The attendance record could not be saved."), "error");
    }
}

async function editAttendance(id) {
    const record = attendanceData.find(item => item.id === id);
    if (!record) return;

    const { value: form } = await Swal.fire({
        title: "Edit Attendance",
        html: `
        <select id="status" class="swal2-input">
            <option value="Present" ${record.status === "Present" ? "selected" : ""}>Present</option>
            <option value="Absent" ${record.status === "Absent" ? "selected" : ""}>Absent</option>
            <option value="Late" ${record.status === "Late" ? "selected" : ""}>Late</option>
            <option value="Excused" ${record.status === "Excused" ? "selected" : ""}>Excused</option>
        </select>
        <input id="time_in" class="swal2-input" type="time" value="${record.time_in ?? ""}">
        <input id="remarks" class="swal2-input" value="${record.remarks ?? ""}" placeholder="Remarks">
        `,
        showCancelButton: true,
        preConfirm: () => ({
            status: document.getElementById("status").value,
            time_in: document.getElementById("time_in").value,
            remarks: document.getElementById("remarks").value
        })
    });

    if (!form) return;

    try {
        const res = await fetch(`${API}/attendance/${id}`, {
            method: "PUT",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(form)
        });

        await readJson(res);
        Swal.fire("Attendance Updated", "The attendance record was updated.", "success");
        loadAttendance();
    } catch (err) {
        Swal.fire("Update Failed", apiError(err, "The attendance record could not be updated."), "error");
    }
}

async function deleteAttendance(id) {
    const result = await Swal.fire({
        title: "Delete attendance record?",
        text: "This action cannot be undone.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Delete"
    });

    if (!result.isConfirmed) return;

    try {
        const res = await fetch(`${API}/attendance/${id}`, {
            method: "DELETE",
            headers: authHeaders()
        });

        await readJson(res);
        Swal.fire("Deleted", "The attendance record was deleted.", "success");
        loadAttendance();
    } catch (err) {
        Swal.fire("Delete Failed", apiError(err, "The attendance record could not be deleted."), "error");
    }
}
