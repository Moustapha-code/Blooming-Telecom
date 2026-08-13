/* import-pdf.js
 * - PDF text extraction via PDF.js
 * - OCR for images + scanned PDF pages via Tesseract.js
 * - Queue + Excel export via SheetJS
 */

pdfjsLib.GlobalWorkerOptions.workerSrc =
    "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

const ui = {
    statusBadge: document.getElementById("statusBadge"),
    loadingInfo: document.getElementById("loadingInfo"),
    loadingText: document.getElementById("loadingText"),
    pickFilesBtn: document.getElementById("pickFilesBtn"),
    fileInput: document.getElementById("fileInput"),
    dropzone: document.getElementById("dropzone"),
    enableOcrForPdf: document.getElementById("enableOcrForPdf"),
    ocrLang: document.getElementById("ocrLang"),
    ocrPageLimit: document.getElementById("ocrPageLimit"),
    clearCurrentBtn: document.getElementById("clearCurrentBtn"),

    dataBody: document.getElementById("dataBody"),
    selectAll: document.getElementById("selectAll"),

    selectedCount: document.getElementById("selectedCount"),
    queueCount: document.getElementById("queueCount"),
    addToQueueBtn: document.getElementById("addToQueueBtn"),
    exportExcelBtn: document.getElementById("exportExcelBtn"),
    clearQueueBtn: document.getElementById("clearQueueBtn"),
};

let previewItems = []; // items currently displayed
let queueItems = [];   // accumulated items user wants to export

// Each item: { id, sourceType: 'PDF'|'IMAGE'|'PDF_OCR', fileName, page, text, confidence }

function setStatus(type, text) {
    // type: 'ready'|'loading'|'success'|'error'
    ui.statusBadge.className = "status-badge " + (
        type === "loading" ? "badge-warning" :
            type === "success" ? "badge-success" :
                type === "error" ? "badge-danger" : "badge-secondary"
    );
    ui.statusBadge.textContent = text;

    const showLoading = type === "loading";
    ui.loadingInfo.classList.toggle("hidden", !showLoading);
}

function escapeHtml(str) {
    return (str ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function uid() {
    return Math.random().toString(16).slice(2) + Date.now().toString(16);
}

function clearPreview() {
    previewItems = [];
    ui.dataBody.innerHTML = `
    <tr>
      <td colspan="5" style="text-align:center; color: var(--text-muted);">
        No data yet. Upload a PDF or image (or drop it here).
      </td>
    </tr>`;
    ui.selectAll.checked = false;
    updateSelectionUI();
    setStatus("ready", "Ready");
}

function updateQueueUI() {
    ui.queueCount.textContent = String(queueItems.length);
    ui.exportExcelBtn.disabled = queueItems.length === 0;
    ui.clearQueueBtn.style.display = queueItems.length ? "inline-block" : "none";
}

function updateSelectionUI() {
    const selected = document.querySelectorAll(".rowCheck:checked").length;
    ui.selectedCount.textContent = String(selected);
    ui.addToQueueBtn.disabled = selected === 0;
}

function renderPreviewTable(items) {
    if (!items.length) {
        clearPreview();
        return;
    }

    ui.dataBody.innerHTML = items.map((it, idx) => {
        const conf = (it.confidence == null) ? "-" : `${Math.round(it.confidence)}%`;
        return `
      <tr>
        <td><input type="checkbox" class="rowCheck" data-id="${it.id}"></td>
        <td><span class="pill small">${escapeHtml(it.sourceType)}</span><div class="muted small">${escapeHtml(it.fileName)}</div></td>
        <td>${it.page ?? "-"}</td>
        <td style="white-space: pre-wrap;">${escapeHtml(it.text)}</td>
        <td>${conf}</td>
      </tr>
    `;
    }).join("");

    // bind row check
    document.querySelectorAll(".rowCheck").forEach(chk => {
        chk.addEventListener("change", updateSelectionUI);
    });

    ui.selectAll.checked = false;
    updateSelectionUI();
}

async function extractTextFromPdf(file) {
    setStatus("loading", "Processing PDF...");
    ui.loadingText.textContent = `Extracting text from PDF: ${file.name}`;

    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

    const items = [];
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();

        // Join text items into lines (simple grouping)
        const strings = textContent.items
            .map(it => (it.str || "").trim())
            .filter(Boolean);

        // If the PDF is mostly scanned, strings might be empty/near-empty -> optional OCR
        if (strings.length) {
            strings.forEach(s => {
                items.push({
                    id: uid(),
                    sourceType: "PDF",
                    fileName: file.name,
                    page: pageNum,
                    text: s,
                    confidence: null,
                });
            });
        }
    }

    return { pdf, items };
}

// Render PDF page into canvas -> OCR it
async function ocrPdfPages(pdf, fileName) {
    const doOcr = ui.enableOcrForPdf.checked;
    if (!doOcr) return [];

    const lang = ui.ocrLang.value;
    const limit = Number(ui.ocrPageLimit.value); // 0 = all

    setStatus("loading", "OCR on PDF pages...");
    const ocrItems = [];

    const totalPages = pdf.numPages;
    const maxPages = (limit === 0) ? totalPages : Math.min(totalPages, limit);

    for (let pageNum = 1; pageNum <= maxPages; pageNum++) {
        ui.loadingText.textContent = `OCR page ${pageNum}/${maxPages} (${fileName})...`;

        const page = await pdf.getPage(pageNum);
        const viewport = page.getViewport({ scale: 2.0 });

        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        await page.render({ canvasContext: ctx, viewport }).promise;

        const imgDataUrl = canvas.toDataURL("image/png");

        const result = await Tesseract.recognize(imgDataUrl, lang, {
            logger: () => { }
        });

        const text = (result?.data?.text || "").trim();
        const conf = result?.data?.confidence; // 0..100 usually

        if (text) {
            // split into non-empty lines for checkbox UX
            text.split("\n")
                .map(l => l.trim())
                .filter(Boolean)
                .forEach(line => {
                    ocrItems.push({
                        id: uid(),
                        sourceType: "PDF_OCR",
                        fileName,
                        page: pageNum,
                        text: line,
                        confidence: conf ?? null,
                    });
                });
        }
    }

    return ocrItems;
}

async function ocrImageFile(file) {
    const lang = ui.ocrLang.value;
    setStatus("loading", "OCR on image...");
    ui.loadingText.textContent = `OCR image: ${file.name}`;

    const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });

    const result = await Tesseract.recognize(dataUrl, lang, {
        logger: () => { }
    });

    const text = (result?.data?.text || "").trim();
    const conf = result?.data?.confidence;

    const items = [];
    if (text) {
        text.split("\n")
            .map(l => l.trim())
            .filter(Boolean)
            .forEach(line => {
                items.push({
                    id: uid(),
                    sourceType: "IMAGE",
                    fileName: file.name,
                    page: "-",
                    text: line,
                    confidence: conf ?? null,
                });
            });
    }
    return items;
}

async function handleFiles(files) {
    try {
        clearPreview();

        const allNewItems = [];

        for (const file of files) {
            const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");
            const isImage = file.type.startsWith("image/");

            if (isPdf) {
                const { pdf, items } = await extractTextFromPdf(file);
                allNewItems.push(...items);

                // OCR optional
                const ocrItems = await ocrPdfPages(pdf, file.name);
                allNewItems.push(...ocrItems);

            } else if (isImage) {
                const items = await ocrImageFile(file);
                allNewItems.push(...items);
            }
        }

        previewItems = allNewItems;

        renderPreviewTable(previewItems);
        setStatus("success", `Done (${previewItems.length} lines)`);

    } catch (err) {
        console.error(err);
        setStatus("error", "Error");
        ui.loadingText.textContent = "Failed to process files.";
        alert("Error processing files. Check console for details.");
    } finally {
        ui.loadingInfo.classList.add("hidden");
    }
}

// Queue actions
function addSelectedToQueue() {
    const selectedIds = Array.from(document.querySelectorAll(".rowCheck:checked"))
        .map(chk => chk.getAttribute("data-id"));

    const selectedItems = previewItems.filter(it => selectedIds.includes(it.id));
    if (!selectedItems.length) return;

    // avoid duplicates by id
    const existing = new Set(queueItems.map(q => q.id));
    selectedItems.forEach(it => {
        if (!existing.has(it.id)) queueItems.push(it);
    });

    updateQueueUI();
    // uncheck after add
    ui.selectAll.checked = false;
    document.querySelectorAll(".rowCheck").forEach(c => (c.checked = false));
    updateSelectionUI();
}

function clearQueue() {
    queueItems = [];
    updateQueueUI();
}

function exportQueueToExcel() {
    if (!queueItems.length) return;

    const data = queueItems.map(it => ({
        Source: it.sourceType,
        Fichier: it.fileName,
        Page: it.page,
        Texte: it.text,
        Confiance: (it.confidence == null ? "" : Math.round(it.confidence)),
    }));

    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Extraction");

    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `extraction_${today}.xlsx`);
}

// UI bindings
ui.pickFilesBtn.addEventListener("click", () => ui.fileInput.click());
ui.fileInput.addEventListener("change", (e) => {
    const files = Array.from(e.target.files || []);
    if (files.length) handleFiles(files);
    e.target.value = ""; // reset
});

ui.clearCurrentBtn.addEventListener("click", clearPreview);

ui.selectAll.addEventListener("change", (e) => {
    const checked = e.target.checked;
    document.querySelectorAll(".rowCheck").forEach(c => (c.checked = checked));
    updateSelectionUI();
});

ui.addToQueueBtn.addEventListener("click", addSelectedToQueue);
ui.clearQueueBtn.addEventListener("click", clearQueue);
ui.exportExcelBtn.addEventListener("click", exportQueueToExcel);

// Drag & drop
ui.dropzone.addEventListener("dragover", (e) => {
    e.preventDefault();
    ui.dropzone.classList.add("dragover");
});
ui.dropzone.addEventListener("dragleave", () => ui.dropzone.classList.remove("dragover"));
ui.dropzone.addEventListener("drop", (e) => {
    e.preventDefault();
    ui.dropzone.classList.remove("dragover");
    const files = Array.from(e.dataTransfer.files || []);
    if (files.length) handleFiles(files);
});

setStatus("ready", "Ready");
updateQueueUI();
