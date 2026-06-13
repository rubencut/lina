let serverPages = [];
const pageLabels = {
    dashboard: "Dashboard",
    users: "Users",
    classrooms: "Classrooms",
    reports: "Reports",
    qr: "QR Codes"
};

if (!token || isSessionExpired()) {
    clearSession();
    window.location.replace("index.html");
}

async function setupNavigation() {
    const res = await fetch(`${API}/dashboard`, {
        headers: authHeaders()
    });
    const dashboard = await readJson(res);
    serverPages = dashboard.allowed_pages || [];

    document.querySelector(".app-header h1").innerText = `${displayStatus(dashboard.role)} Portal`;

    const nav = document.querySelector(".nav");
    nav.innerHTML = "";

    serverPages.forEach(page => {
        const menu = document.createElement("a");
        menu.className = "menu";
        menu.dataset.page = page;
        menu.textContent = pageLabels[page] || displayStatus(page);
        menu.addEventListener("click", () => showPage(page));
        nav.appendChild(menu);
    });

    const savedPage = localStorage.getItem("activePage");
    showPage(serverPages.includes(savedPage) ? savedPage : serverPages[0]);
}

function showPage(page) {
    if (!serverPages.includes(page)) {
        page = serverPages[0] || "dashboard";
    }

    localStorage.setItem("activePage", page);

    document.querySelectorAll(".menu").forEach(menu => {
        menu.classList.toggle("active", menu.dataset.page === page);
    });

    document.querySelectorAll(".section").forEach(section => {
        section.classList.toggle("active", section.id === page);
    });

    if (page === "dashboard") loadDashboard();
    if (page === "users") loadUsers();
    if (page === "classrooms") loadClassrooms();
    if (page === "reports") loadReports();
    if (page === "qr") loadQrCodes();
}
