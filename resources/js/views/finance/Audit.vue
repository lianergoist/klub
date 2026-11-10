<template>
    <div>
        <p class="small muted">Alle ændringer i medlemsdata, roller, betalinger og regnskab bliver logget med hvem og hvornår. Revisor kan se loggen, men ikke ændre den.</p>
        <div class="card" style="padding:0 16px">
            <div class="table-wrap"><table>
                <thead><tr><th>Tidspunkt</th><th>Hvem</th><th>Handling</th><th>Objekt</th><th>Detaljer</th></tr></thead>
                <tbody><tr v-for="l in rows" :key="l.id">
                    <td class="small" style="white-space:nowrap">{{ tidspunkt(l.at) }}</td><td>{{ l.by }}</td><td><span class="tag">{{ labels[l.action] || l.action }}</span></td><td class="small muted">{{ l.subject }}</td>
                    <td class="small"><code v-if="l.changes" style="white-space:pre-wrap;word-break:break-word">{{ short(l.changes) }}</code></td>
                </tr></tbody>
            </table></div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { api, tidspunkt } from '../../api';
const rows = ref([]);
const labels = { 'entry.created': 'Postering oprettet', 'entry.reversed': 'Modpostering', 'payment.registered': 'Betaling', 'payment.refunded': 'Refusion', 'member.updated': 'Medlem rettet', 'member.viewed': 'Medlem set', 'member.approved': 'Medlem godkendt', 'member.registered': 'Indmeldelse', 'role.granted': 'Rolle tildelt', 'role.ended': 'Rolle afsluttet', 'activity.created': 'Aktivitet oprettet', 'activity.updated': 'Aktivitet rettet', 'activity.cancelled': 'Aktivitet aflyst', 'budget.saved': 'Budget gemt', 'membership.generated': 'Kontingent opkrævet', 'invoice.reminded': 'Påmindelse', 'members.exported': 'Medlemsliste eksporteret', 'member.data_exported': 'Dataudtræk', 'product.ordered': 'Tilkøb', 'category.created': 'Kategori oprettet', 'category.renamed': 'Kategori rettet', 'member.added_to_household': 'Familiemedlem tilføjet', 'price.set': 'Pris ændret', 'price.deleted': 'Kommende pris fjernet', 'payouts.imported': 'MobilePay-afregning indlæst', 'guest.booked': 'Gæst tilmeldt', 'guest.converted': 'Gæst meldt ind', 'membership.guest_credit': 'Gæstekøb modregnet', 'member.activated_by_payment': 'Medlem aktiveret (betalt)', 'household.members_added': 'Husstand udvidet', 'household.upgrade_requested': 'Udvidelse af husstand bestilt', 'household.upgrade_cancelled': 'Udvidelse annulleret', 'post.created': 'Opslag oprettet', 'post.hidden': 'Opslag skjult', 'post.shown': 'Opslag vist igen', 'post.reported': 'Opslag anmeldt', 'message.reported': 'Besked anmeldt', 'user.blocked': 'Medlem blokeret', 'conversation.group_created': 'Gruppe oprettet' };
const short = (c) => { const s = JSON.stringify(c); return s.length > 160 ? s.slice(0, 160) + '…' : s; };
onMounted(async () => { rows.value = await api('finance/audit'); });
</script>
