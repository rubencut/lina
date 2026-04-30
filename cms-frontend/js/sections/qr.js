let qrStream = null;
let qrScanning = false;
const qrCanvas = document.createElement("canvas");
const qrContext = qrCanvas.getContext("2d", { willReadFrequently: true });

async function startScanner() {
    try {
        const video = document.getElementById("qrVideo");
        qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        video.srcObject = qrStream;
        await video.play();
        qrScanning = true;
        scanQr();
    } catch (err) {
        Swal.fire("Camera Error", err.message, "error");
    }
}

function stopScanner() {
    if (qrStream) {
        qrStream.getTracks().forEach(track => track.stop());
        qrStream = null;
    }

    document.getElementById("qrVideo").srcObject = null;
    qrScanning = false;
}

function scanQr() {
    if (!qrScanning) return;

    const video = document.getElementById("qrVideo");
    if (!video.videoWidth) {
        requestAnimationFrame(scanQr);
        return;
    }

    qrCanvas.width = video.videoWidth;
    qrCanvas.height = video.videoHeight;
    qrContext.drawImage(video, 0, 0, qrCanvas.width, qrCanvas.height);

    const imageData = qrContext.getImageData(0, 0, qrCanvas.width, qrCanvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

    if (!code) {
        requestAnimationFrame(scanQr);
        return;
    }

    stopScanner();
    markQrAttendance(code.data);
}

async function markQrAttendance(qrCode) {
    const { value: classroomId } = await Swal.fire({
        title: "Classroom ID",
        input: "number",
        showCancelButton: true,
        preConfirm: value => {
            if (!value) return showFormError("Classroom ID is required");
            return value;
        }
    });

    if (!classroomId) return;

    try {
        const res = await fetch(`${API}/qr/scan`, {
            method: "POST",
            headers: {
                ...authHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                qr_code: qrCode,
                classroom_id: classroomId
            })
        });

        await readJson(res);
        Swal.fire("Attendance Marked", "QR attendance was recorded.", "success");
        loadDashboard();
    } catch (err) {
        Swal.fire("Scan Failed", apiError(err, "The QR code could not be processed."), "error");
    }
}
