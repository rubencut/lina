async function dailyReport() {
    const { value: date } = await Swal.fire({
        title: "Daily Attendance Report",
        input: "date",
        inputValue: new Date().toISOString().slice(0, 10),
        showCancelButton: true,
        preConfirm: value => {
            if (!value) return showFormError("Please choose a date");
            return value;
        }
    });

    if (!date) return;

    try {
        const res = await fetch(`${API}/reports/daily?date=${date}`, {
            headers: authHeaders()
        });
        const report = await readJson(res);

        let html = `
        <tr>
            <th>Date</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th>
        </tr>
        <tr>
            <td>${report.date}</td>
            <td>${report.total}</td>
            <td>${report.present}</td>
            <td>${report.absent}</td>
            <td>${report.late}</td>
            <td>${report.excused}</td>
        </tr>`;

        document.getElementById("reportsTable").innerHTML = html;
    } catch (err) {
        Swal.fire("Report Failed", apiError(err, "The report could not be loaded."), "error");
    }
}

function downloadCsv() {
    const today = new Date().toISOString().slice(0, 10);
    downloadFile(`reports/export-csv?report_type=daily&date=${today}`, `attendance-${today}.csv`);
}

async function printDaily() {
    const today = new Date().toISOString().slice(0, 10);
    const win = window.open("about:blank", "_blank");

    try {
        const res = await fetch(`${API}/print/daily-attendance?date=${today}`, {
            headers: authHeaders()
        });

        if (!res.ok) throw await readJson(res);

        win.document.write(await res.text());
        win.document.close();
        win.print();
    } catch (err) {
        if (win) win.close();
        Swal.fire("Print Failed", apiError(err, "The report could not be opened."), "error");
    }
}
