/**
 * Background script for Blooming PDF Extractor
 */

chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
    if (changeInfo.status === 'complete' && tab.url && tab.url.toLowerCase().endsWith('.pdf')) {
        console.log('PDF detected:', tab.url);

        // Notify the user or automatically trigger extraction
        chrome.action.setBadgeText({ text: 'PDF', tabId: tabId });
        chrome.action.setBadgeBackgroundColor({ color: '#37352f' });
    }
});

// Listen for action click (optional, but good for manual trigger)
chrome.action.onClicked.addListener((tab) => {
    if (tab.url && tab.url.toLowerCase().endsWith('.pdf')) {
        extractPDF(tab);
    }
});

async function extractPDF(tab) {
    // Opening the import page with the PDF URL as a parameter
    // The page will handle the extraction client-side using a cross-origin fetch if permitted
    // or we can extract here if we bundle pdf.js
    const importUrl = `http://localhost/blooming/pages/import-pdf.php?pdf_url=${encodeURIComponent(tab.url)}`;
    chrome.tabs.create({ url: importUrl });
}
