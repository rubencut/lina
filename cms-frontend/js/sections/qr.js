async function loadQrCodes() {
    const table = document.getElementById("qrTable");
    if (!table) return;

    const res = await fetch(`${API}/qr/users?per_page=100`, {
        headers: authHeaders()
    });
    const data = await readJson(res);
    const users = data.data || [];

    let html = `
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Classroom</th>
        <th>QR Code</th>
        <th>QR Status</th>
        <th>Action</th>
    </tr>`;

    if (!users.length) {
        html += `<tr><td colspan="6">No users available for QR codes.</td></tr>`;
    }

    users.forEach(user => {
        html += `
        <tr>
            <td>${user.name}</td>
            <td>${user.email ?? "-"}</td>
            <td>${user.classroom?.name ?? "-"}</td>
            <td>
                ${user.qr_image
                    ? `<button class="qr-thumb-button" onclick="viewQr(${user.id})" title="Open ${user.name} QR code">
                        <img src="${user.qr_image}" alt="QR code for ${user.name}">
                    </button>`
                    : "-"
                }
            </td>
            <td>${user.qr_code ? "Generated" : "Not generated"}</td>
            <td>
                <button class="action-btn edit" onclick="generateQr(${user.id})">${user.qr_code ? "View" : "Generate"}</button>
                ${user.qr_code ? `<button class="action-btn edit" onclick="downloadQr(${user.id})">Download</button>` : ""}
            </td>
        </tr>`;
    });

    table.innerHTML = html;
}

async function generateQr(userId) {
    try {
        const res = await fetch(`${API}/qr/generate/${userId}`, {
            method: "POST",
            headers: authHeaders()
        });
        const data = await readJson(res);

        await showQr(data);
        loadQrCodes();
    } catch (err) {
        Swal.fire("QR Failed", apiError(err, "The QR code could not be generated."), "error");
    }
}

async function viewQr(userId) {
    try {
        const data = await apiGet(`qr/get/${userId}`);
        showQr(data);
    } catch (err) {
        Swal.fire("QR Failed", apiError(err, "The QR code could not be loaded."), "error");
    }
}

function showQr(data) {
    return Swal.fire({
        title: data.user_name,
        html: `
            <div class="qr-card">
                <img src="${data.qr_image}" alt="QR code for ${data.user_name}">
                <p class="modal-muted">${data.qr_code}</p>
            </div>
        `,
        showDenyButton: true,
        confirmButtonText: "Close",
        denyButtonText: "Download",
        preDeny: () => {
            downloadQr(data.user_id);
            return false;
        }
    });
}

function downloadQr(userId) {
    downloadFile(`qr/download/${userId}`, `qr-code-${userId}.png`);
}

async function printQrCodes() {
    const win = window.open("about:blank", "_blank");

    try {
        const res = await fetch(`${API}/qr/print-all`, {
            headers: authHeaders()
        });

        if (!res.ok) throw await readJson(res);

        win.document.write(await res.text());
        win.document.close();
        win.print();
    } catch (err) {
        if (win) win.close();
        Swal.fire("Print Failed", apiError(err, "The QR codes could not be printed."), "error");
    }
}
