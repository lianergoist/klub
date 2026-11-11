// Fælles logik for pakker (medlemskaber og tilkøb). Serveren tjekker det samme – dette er kun for at kunne vise fejl med det samme.

/** Alder på en given dato (fx pakkens startdato). */
export function ageOn(birthdate, date) {
    if (!birthdate) return null;
    const b = new Date(birthdate + 'T12:00:00'); const d = new Date((date || new Date().toISOString().slice(0, 10)) + 'T12:00:00');
    let a = d.getFullYear() - b.getFullYear();
    if (d.getMonth() < b.getMonth() || (d.getMonth() === b.getMonth() && d.getDate() < b.getDate())) a--;
    return a;
}

export const categoryFor = (categories, age) => age === null ? null : categories.find(c => age >= c.min_age && (c.max_age === null || age <= c.max_age)) || null;

/** null hvis personerne passer til pakken, ellers en forklaring. people: [{ name, birthdate }] */
export function compositionError(pkg, people, categories) {
    const n = people.length;
    const range = (a, b) => a === b ? `${a}` : `${a}–${b}`;
    if (n < pkg.total_min || (pkg.total_max !== null && n > pkg.total_max)) {
        return `${pkg.name} dækker ${pkg.composition_label}. Du har valgt ${n}.`;
    }
    const counts = {};
    for (const p of people) {
        if (!p.birthdate) return `Angiv fødselsdato for ${p.name || 'alle personer'}.`;
        const age = ageOn(p.birthdate, pkg.valid_from);
        const slot = pkg.slots.find(s => { const c = categories.find(c => c.id === s.category_id); return c && age >= c.min_age && (c.max_age === null || age <= c.max_age); });
        if (!slot) return `${p.name || 'En person'} (${age} år) passer ikke til ${pkg.name}.`;
        counts[slot.category_id] = (counts[slot.category_id] || 0) + 1;
    }
    for (const s of pkg.slots) {
        const c = counts[s.category_id] || 0;
        if (c < s.min || c > s.max) {
            const cat = categories.find(x => x.id === s.category_id);
            return `${pkg.name} kræver ${range(s.min, s.max)} ${(s.max === 1 ? cat.name : cat.plural).toLowerCase()}. Der er ${c}.`;
        }
    }
    return null;
}

/** Kan pakken overhovedet dække flere personer end den/dem, der allerede er valgt? */
export const allowsMore = (pkg, n) => pkg.total_max === null || n < pkg.total_max;
