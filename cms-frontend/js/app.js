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
