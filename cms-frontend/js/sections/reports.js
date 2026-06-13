async function loadReports() {
    const table = document.getElementById("reportsTable");
    if (!table) return;

    const actions = document.getElementById("reportActions");
    if (actions) {
        actions.style.display = currentUser().role === "student_employee_participant" ? "none" : "";
    }

    const res = await fetch(`${API}/reports?per_page=100`, {
        headers: authHeaders()
    });
    const data = await readJson(res);
    const rows = data.data || [];

    let html = `
    <tr>
        <th>Date</th>
        <th>Classroom</th>
        <th>Teacher</th>
        <th>Description</th>
        <th>Submitted</th>
        <th>Action</th>
    </tr>`;

    if (!rows.length) {
        html += `<tr><td colspan="6">No submitted attendance yet.</td></tr>`;
    }

    rows.forEach(row => {
        html += `
        <tr>
            <td>${formatDate(row.date)}</td>
            <td>${row.classroom?.name ?? "-"}</td>
            <td>${row.classroom?.teacher?.name ?? "-"}</td>
            <td>${row.classroom?.description ?? "-"}</td>
            <td>${formatDate(row.submitted_at)}</td>
            <td><button class="action-btn edit" onclick="viewReport(${row.id})">View</button></td>
        </tr>`;
    });

    table.innerHTML = html;
}

async function viewReport(id) {
    try {
        const data = await apiGet(`reports/${id}`);
        const report = data.report || {};
        const rows = data.attendance || [];
        const title = report.classroom?.name ?? "Report";

        let html = `
        <p class="modal-muted">${report.classroom?.description ?? ""}</p>
        <table class="modal-table">
            <tr>
                <th>Student</th>
                <th>Email</th>
                <th>Status</th>
                <th>Time In</th>
            </tr>`;

        if (!rows.length) {
            html += `<tr><td colspan="4">No attendance records found.</td></tr>`;
        }

        rows.forEach(row => {
            html += `
            <tr>
                <td>${row.user?.name ?? "-"}</td>
                <td>${row.user?.email ?? "-"}</td>
                <td>${statusBadge(row.status)}</td>
                <td>${row.time_in ?? "-"}</td>
            </tr>`;
        });

        html += `</table>`;

        await Swal.fire({
            title,
            width: 950,
            html,
            showDenyButton: true,
            denyButtonText: "Print",
            confirmButtonText: "Close",
            preDeny: () => {
                printReport(title, html);
                return false;
            }
        });
    } catch (err) {
        Swal.fire("Report Failed", apiError(err, "The report could not be loaded."), "error");
    }
}

function printReport(title, html) {
    const win = window.open("about:blank", "_blank");
    if (!win) return;

    win.document.write(`
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }
                h1 { margin: 0 0 8px; text-align: center; }
                p { text-align: center; color: #4b5563; }
                table { width: 100%; border-collapse: collapse; margin-top: 24px; }
                th, td { border: 1px solid #d1d5db; padding: 10px; text-align: left; }
                th { background: #f3f4f6; font-size: 12px; text-transform: uppercase; }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            ${html}
        </body>
        </html>
    `);
    win.document.close();
    win.focus();
    win.print();
}

function downloadExcel() {
    const today = new Date().toISOString().slice(0, 10);
    downloadFile(`reports/export?report_type=reports&format=Excel&date_from=2000-01-01&date_to=${today}`, `classroom-reports-${today}.xlsx`);
}

function downloadPdf() {
    const today = new Date().toISOString().slice(0, 10);
    downloadFile(`reports/export?report_type=reports&format=PDF&date_from=2000-01-01&date_to=${today}`, `classroom-reports-${today}.pdf`);
}

function downloadCsv() {
    const today = new Date().toISOString().slice(0, 10);
    downloadFile(`reports/export?report_type=reports&format=CSV&date_from=2000-01-01&date_to=${today}`, `classroom-reports-${today}.csv`);
}
