/*
 * Affiche la valeur et le pourcentage directement sur les graphiques.
 *
 * Écrit comme greffon Chart.js maison plutôt qu'en ajoutant
 * chartjs-plugin-datalabels : une dépendance CDN de moins, et le
 * positionnement peut être réglé par type de graphique.
 *
 * Chargé après Chart.js, il s'applique à tous les graphiques de la page.
 */
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    // En dessous de ce pourcentage, une part de camembert est trop
    // étroite pour contenir son étiquette : elle se chevaucherait avec
    // les voisines. L'infobulle reste disponible au survol.
    const MIN_ARC_PERCENT = 4;

    function readableColor() {
        const muted = getComputedStyle(document.body).getPropertyValue('--text-muted').trim();
        return muted || '#64748b';
    }

    const ValueLabels = {
        id: 'valueLabels',

        afterDatasetsDraw(chart) {
            const ctx = chart.ctx;
            const horizontal = chart.options.indexAxis === 'y';

            chart.data.datasets.forEach(function (dataset, datasetIndex) {
                const meta = chart.getDatasetMeta(datasetIndex);
                if (meta.hidden) {
                    return;
                }

                const values = dataset.data.map(function (v) { return Number(v) || 0; });
                const total  = values.reduce(function (a, b) { return a + b; }, 0);
                if (total <= 0) {
                    return;
                }

                meta.data.forEach(function (element, index) {
                    const value = values[index];
                    if (!value) {
                        return; // ne rien écrire sur une valeur nulle
                    }

                    const percent = Math.round((value * 100) / total);
                    const label   = value + ' (' + percent + '%)';
                    const type    = meta.type || chart.config.type;
                    const point   = element.tooltipPosition();

                    ctx.save();
                    ctx.font = '600 11px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
                    ctx.textBaseline = 'middle';

                    if (type === 'doughnut' || type === 'pie') {
                        if (percent < MIN_ARC_PERCENT) {
                            ctx.restore();
                            return;
                        }
                        // Texte blanc au centre de la part, cerné de noir
                        // translucide pour rester lisible sur toute couleur.
                        ctx.textAlign = 'center';
                        ctx.lineWidth = 3;
                        ctx.strokeStyle = 'rgba(15, 23, 42, .55)';
                        ctx.strokeText(label, point.x, point.y);
                        ctx.fillStyle = '#ffffff';
                        ctx.fillText(label, point.x, point.y);
                    } else if (type === 'bar' && horizontal) {
                        ctx.textAlign = 'left';
                        ctx.fillStyle = readableColor();
                        ctx.fillText(label, element.x + 6, element.y);
                    } else {
                        // Barres verticales et courbes : au-dessus du point.
                        ctx.textAlign = 'center';
                        ctx.fillStyle = readableColor();
                        ctx.fillText(label, point.x, point.y - 10);
                    }

                    ctx.restore();
                });
            });
        }
    };

    Chart.register(ValueLabels);

    // Même information dans l'infobulle, y compris pour les parts trop
    // fines pour être étiquetées.
    Chart.defaults.plugins.tooltip.callbacks.label = function (context) {
        const values = context.dataset.data.map(function (v) { return Number(v) || 0; });
        const total  = values.reduce(function (a, b) { return a + b; }, 0);
        const value  = Number(context.parsed.y ?? context.parsed ?? 0) || values[context.dataIndex] || 0;
        const percent = total > 0 ? Math.round((value * 100) / total) : 0;
        const name = context.dataset.label && context.chart.config.type !== 'doughnut'
            && context.chart.config.type !== 'pie'
            ? context.dataset.label + ': '
            : '';
        return ' ' + name + value + ' (' + percent + '%)';
    };
})();
