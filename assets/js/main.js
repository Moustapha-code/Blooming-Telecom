document.addEventListener("DOMContentLoaded", () => {
    // Other initializations can go here if needed
});

/**
 * API Helpers
 */
async function apiCall(endpoint, method = "GET", data = null) {
    const options = {
        method: method,
        headers: {
            "Content-Type": "application/json",
        },
    }

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(endpoint, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || "Échec de l'appel API");
        }

        return result;
    } catch (error) {
        console.error("API Error:", error);
        throw error;
    }
}

/**
 * Alert System
 */
function showAlert(message, type = "success") {
    const alertDiv = document.createElement("div");
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '24px';
    alertDiv.style.right = '24px';
    alertDiv.style.zIndex = '9999';
    alertDiv.className = `badge badge-${type} fade-in`;
    alertDiv.style.padding = '12px 20px';
    alertDiv.style.borderRadius = '8px';
    alertDiv.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
    alertDiv.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;

    document.body.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 500);
    }, 4000);
}

/**
 * Export Logic
 */
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const csv = [];
    const rows = table.querySelectorAll("tr");
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            // Remove extra whitespace and handle quotes
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }

    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
