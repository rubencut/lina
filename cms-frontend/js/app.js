function logout() {
    fetch(`${API}/logout`, {
        method: "POST",
        headers: authHeaders()
    }).finally(() => {
        clearSession();
        window.location.replace("index.html");
    });
}

async function loadDashboard() {
    const statsContainer = document.getElementById("dashboardStats");
    const activityTable = document.getElementById("dashboardActivityTable");

    if (!statsContainer || !activityTable) return;

    const res = await fetch(`${API}/dashboard/summary`, {
        headers: authHeaders()
    });
    const summary = await readJson(res);

    statsContainer.innerHTML = (summary.stats || []).map(item => `
        <div class="dashboard-card">
            <span>${item.label}</span>
            <strong>${item.value}</strong>
        </div>
    `).join("");

    let html = `
    <tr>
        <th>Date</th><th>User</th><th>Classroom</th><th>Status</th><th>Time In</th>
    </tr>`;

    const rows = summary.recent_attendance || [];
    if (rows.length === 0) {
        html += `<tr><td colspan="5">No attendance activity is available yet.</td></tr>`;
    }

    rows.forEach(row => {
        html += `
        <tr>
            <td>${formatDate(row.date)}</td>
            <td>${row.user ?? "-"}</td>
            <td>${row.classroom ?? "-"}</td>
            <td>${displayStatus(row.status)}</td>
            <td>${row.time_in ?? "-"}</td>
        </tr>`;
    });

    activityTable.innerHTML = html;
    loadDashboardQrCodes();
}

async function loadDashboardQrCodes() {
    const grid = document.getElementById("dashboardQrGrid");
    if (!grid) return;

    try {
        const res = await fetch(`${API}/qr/users?per_page=24`, {
            headers: authHeaders()
        });
        const data = await readJson(res);
        const students = (data.data || []).filter(user => user.role === "student_employee_participant");

        if (!students.length) {
            grid.innerHTML = `<p class="modal-muted">No student QR codes are available yet.</p>`;
            return;
        }

        grid.innerHTML = students.map(user => `
            <div class="dashboard-qr-card">
                ${user.qr_image
                    ? `<button class="qr-image-button" onclick="viewQr(${user.id})" title="Open ${user.name} QR code">
                        <img src="${user.qr_image}" alt="QR code for ${user.name}">
                    </button>`
                    : `<div class="qr-placeholder">No QR</div>`
                }
                <strong>${user.name}</strong>
                <span>${user.classroom?.name ?? "No classroom"}</span>
                ${user.qr_image
                    ? `<button class="action-btn edit" onclick="viewQr(${user.id})">Open</button>`
                    : `<button class="action-btn edit" onclick="generateDashboardQr(${user.id})">Generate</button>`
                }
            </div>
        `).join("");
    } catch (err) {
        grid.innerHTML = `<p class="modal-muted">Student QR codes could not be loaded.</p>`;
    }
}

async function generateDashboardQr(userId) {
    await generateQr(userId);
    loadDashboard();
}

async function initializeDashboard() {
    try {
        await setupNavigation();
    } catch (err) {
        clearSession();
        window.location.replace("index.html");
    }
}

initializeDashboard();
