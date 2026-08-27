export const aiCriterionStatus = Object.freeze({
    meets: { label: "Se ajusta", tone: "success" },
    partially_meets: { label: "Se ajusta parcialmente", tone: "warning" },
    does_not_meet: { label: "No se ajusta", tone: "danger" },
    not_evidenced: { label: "No evidenciado", tone: "neutral" },
    not_applicable: { label: "No aplica", tone: "neutral" },
});

export function criterionStatusPresentation(status) {
    return aiCriterionStatus[status] || {
        label: "Sin clasificación",
        tone: "neutral",
    };
}

export function groupAiReportCriteria(report) {
    const groups = new Map();
    const criteria = Array.isArray(report?.criteria_assessment)
        ? report.criteria_assessment
        : [];

    criteria.forEach((item) => {
        const dimension = item?.dimension || "Otros criterios";
        if (!groups.has(dimension)) groups.set(dimension, []);
        groups.get(dimension).push(item);
    });

    return Array.from(groups, ([dimension, items]) => ({ dimension, items }));
}

export function aiReportStatistics(report) {
    const criteria = Array.isArray(report?.criteria_assessment)
        ? report.criteria_assessment
        : [];
    const count = (status) =>
        criteria.filter((item) => item?.status === status).length;
    const meets = count("meets");
    const partiallyMeets = count("partially_meets");
    const doesNotMeet = count("does_not_meet");
    const notEvidenced = count("not_evidenced");
    const notApplicable = count("not_applicable");
    const applicable = Math.max(0, criteria.length - notApplicable);
    const evidenced = Math.max(0, applicable - notEvidenced);

    return {
        criteria_total: criteria.length,
        meets,
        partially_meets: partiallyMeets,
        does_not_meet: doesNotMeet,
        not_evidenced: notEvidenced,
        not_applicable: notApplicable,
        needs_attention: partiallyMeets + doesNotMeet + notEvidenced,
        compliance_percentage: applicable
            ? Math.round(((meets + partiallyMeets * 0.5) / applicable) * 100)
            : 0,
        evidence_coverage_percentage: applicable
            ? Math.round((evidenced / applicable) * 100)
            : 0,
        miscellaneous_findings: Array.isArray(report?.miscellaneous_findings)
            ? report.miscellaneous_findings.length
            : 0,
    };
}

export function severityPresentation(severity) {
    return {
        critical: "Crítica",
        important: "Importante",
        suggestion: "Sugerencia",
    }[severity] || "Hallazgo";
}
