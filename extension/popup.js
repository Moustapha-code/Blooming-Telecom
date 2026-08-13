chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    const tab = tabs[0];
    const btn = document.getElementById('extractBtn');
    const msg = document.getElementById('msg');

    if (tab.url && tab.url.toLowerCase().endsWith('.pdf')) {
        msg.innerText = "PDF Detected!";
        btn.style.display = "block";
        btn.onclick = () => {
            const importUrl = `http://localhost/blooming/pages/import-pdf.php?pdf_url=${encodeURIComponent(tab.url)}`;
            chrome.tabs.create({ url: importUrl });
        };
    } else {
        msg.innerText = "No PDF detected in this tab.";
    }
});
