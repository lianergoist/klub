<template>
    <div v-if="!p" class="spinner"></div>
    <div v-else>
        <!-- Mobil/app: undersider med tilbage-link i stedet for en fanebjælke -->
        <template v-if="isMobile && cur">
            <RouterLink to="/profil" class="small back">‹ Min profil</RouterLink>
            <h1 style="margin-bottom:14px">{{ curTab.title }}</h1>
        </template>
        <template v-else>
        <h1>{{ p.profile.name }}</h1>
        <p class="muted" style="margin-bottom:14px">
            <span :class="['tag', p.profile.status === 'active' ? 'ok' : 'warn']">{{ p.profile.status_label }}</span>
            <span v-if="p.membership" class="tag ok">{{ p.membership.name }}<template v-if="p.membership.valid_to"> · til {{ dato(p.membership.valid_to) }}</template></span>
            <span v-else-if="p.upcoming" class="tag info">{{ p.upcoming.name }} · fra {{ dato(p.upcoming.valid_from) }}</span>
            <span v-else-if="p.profile.status !== 'pending'" class="tag bad">Intet gyldigt medlemskab</span>
        </p>
        </template>
        <div v-if="!isMobile" class="tabs">
            <RouterLink v-for="t in tabs" :key="t.id" :to="`/profil/${t.id}`" :class="{ on: cur === t.id }">{{ t.label }}</RouterLink>
        </div>

        <template v-if="isMobile && !cur">
            <div v-for="(g, gi) in menuGroups" :key="gi" class="card menu">
                <ul class="list">
                    <li v-for="t in g" :key="t.id"><RouterLink :to="`/profil/${t.id}`" class="mrow">
                        <Icon :name="t.icon" class="mi" />
                        <span class="mtext"><strong>{{ t.title }}</strong><span class="small muted">{{ t.hint }}</span></span>
                        <span v-if="t.badge" :class="['tag', t.badgeCls]">{{ t.badge }}</span>
                        <span class="chev" aria-hidden="true">›</span>
                    </RouterLink></li>
                </ul>
            </div>
            <div class="card menu"><ul class="list"><li><button type="button" class="mrow" @click="openHelp()">
                <Icon name="help" class="mi" />
                <span class="mtext"><strong>Hjælp</strong><span class="small muted">Sådan bruger du systemet</span></span>
                <span class="chev" aria-hidden="true">›</span>
            </button></li><li><a href="/" class="mrow" @click="openSite">
                <Icon name="globe" class="mi" />
                <span class="mtext"><strong>Klubbens hjemmeside</strong><span class="small muted">Priser, kalender og kontakt</span></span>
                <span class="chev" aria-hidden="true">›</span>
            </a></li></ul></div>
            <div class="card"><ThemeSwitch /></div>
            <button class="btn danger block" @click="logout">Log ud</button>
            <p class="small muted" style="margin:12px 4px 0">Logget ind som {{ store.user.name }} ({{ store.user.roles.map(r => roleLabel[r]).join(', ') }})</p>
            <AppVersion style="margin-left:4px" />
        </template>

        <form v-if="cur === 'oplysninger'" class="card stack" @submit.prevent="saveProfile">
            <label class="field"><span>Navn</span><input v-model="f.name" required autocomplete="name"></label>
            <div class="grid c2">
                <label class="field"><span>E-mail</span><input v-model="f.email" type="email" :required="!f.phone" autocomplete="email" autocapitalize="off" spellcheck="false"></label>
                <label class="field"><span>Telefon</span><input v-model="f.phone" type="tel" inputmode="tel" autocomplete="tel"></label>
            </div>
            <label class="field"><span>Adresse</span><input v-model="f.address" autocomplete="address-line1"></label>
            <div class="grid c3 addr">
                <label class="field"><span>Postnr.</span><input v-model="f.postal_code" inputmode="numeric" maxlength="4" autocomplete="postal-code"></label>
                <label class="field"><span>By</span><input v-model="f.city" autocomplete="address-level2"></label>
                <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" type="date" autocomplete="bday"></label>
            </div>
            <h3 style="margin-top:8px">Nødkontakt</h3>
            <div class="grid c2">
                <label class="field"><span>Navn</span><input v-model="f.emergency_name" autocomplete="off"></label>
                <label class="field"><span>Telefon</span><input v-model="f.emergency_phone" type="tel" inputmode="tel" autocomplete="off"></label>
            </div>
            <button class="btn primary block save-btn">Gem</button>
        </form>
        <form v-if="cur === 'oplysninger'" class="card stack" @submit.prevent="changePassword">
            <div class="row between"><h3 style="margin:0">Skift adgangskode</h3><HelpButton topic="adgangskode" /></div>
            <input type="email" :value="f.email || f.phone" autocomplete="username" hidden readonly>
            <label class="field"><span>Nuværende adgangskode</span><input v-model="pw.current_password" type="password" autocomplete="current-password" required></label>
            <div class="grid c2">
                <label class="field"><span>Ny adgangskode</span><input v-model="pw.password" type="password" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span>Gentag ny adgangskode</span><input v-model="pw.password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
            </div>
            <p class="small muted" style="margin:0">Mindst 8 tegn. Andre enheder, du er logget ind på, bliver logget ud.</p>
            <p v-if="pwError" class="banner bad" style="margin:0">{{ pwError }}</p>
            <button class="btn block" :disabled="pwBusy">Skift adgangskode</button>
        </form>

        <div v-if="cur === 'familie'">
            <div v-if="!shop" class="spinner"></div>
            <template v-else>
                <div class="card" style="padding:4px 16px">
                    <ul class="list">
                        <li v-for="m in shop.household" :key="m.id" class="row between" style="flex-wrap:nowrap">
                            <span><strong>{{ m.name }}</strong> <span class="muted small">{{ m.age }} år</span>
                                <span class="small muted" style="display:block">{{ m.membership ? m.membership.name + (m.membership.valid_to ? ' · til ' + dato(m.membership.valid_to) : '') : m.upcoming ? m.upcoming.name + ' · fra ' + dato(m.upcoming.valid_from) : 'Intet gyldigt medlemskab' }}<template v-if="shop.next_plan?.mode === 'next_renewal' && shop.next_plan.people.some(x => x.id === m.id && !x.renews)"> · ikke med fra {{ dato(shop.next_plan.next_from) }}</template></span></span>
                            <span class="row" style="gap:6px;flex-wrap:nowrap">
                            <button v-if="m.id !== p.profile.id && shop.can_add_people && !['resigned', 'guest'].includes(m.status)" class="btn small ghost" @click="openLogin(m)">{{ m.can_login ? 'Login' : 'Eget login' }}</button>
                            <span :class="['tag', m.membership ? 'ok' : m.upcoming ? 'info' : m.status === 'pending' ? 'warn' : 'bad']">{{ m.membership ? 'Medlem' : m.upcoming ? 'Medlem fra ' + dato(m.upcoming.valid_from) : m.status === 'pending' ? 'Afventer betaling' : 'Ikke medlem' }}</span>
                            </span>
                        </li>
                    </ul>
                </div>
                <p class="small muted">Som betaler for husstanden kan du tilmelde alle i familien til aktiviteter og betale deres regninger.</p>

                <!-- Næste sæson: betaleren kan fjerne personer fra husstandens medlemskab (penge flyttes ikke) -->
                <div v-if="shop.next_plan && shop.next_plan.mode !== 'not_renewable'" class="card stack">
                    <h2 style="margin:0">Næste sæson</h2>
                    <template v-if="shop.next_plan.mode === 'next_renewal'">
                        <p class="small muted" style="margin:0">Fra {{ dato(shop.next_plan.next_from) }} fornyes jeres medlemskab som <strong>{{ nextText }}</strong>.
                            Skal nogen ikke være med fra næste sæson, kan du fjerne dem her. De er med resten af denne sæson.</p>
                        <button v-if="!nextEdit" class="btn small" style="align-self:flex-start" @click="nextEdit = true">Ret hvem der er med</button>
                        <NextSeasonEditor v-else :plan="shop.next_plan" url="profile/membership-next/options" save-url="profile/membership-next" @saved="nextEdit = false; loadShop()" />
                    </template>
                    <p v-else class="small muted" style="margin:0">Næste sæsons kontingent er opkrævet ({{ shop.next_plan.upcoming.name }}). Skal nogen ikke være med, så <RouterLink to="/beskeder">skriv til bestyrelsen</RouterLink>.</p>
                    <p class="small muted" style="margin:0">Skal nogen stoppe med det samme eller have penge tilbage, så <RouterLink to="/beskeder">skriv til bestyrelsen</RouterLink>.</p>
                </div>

                <div v-if="openPurchases('membership').length" class="card" style="border-color:var(--warn)">
                    <h2>Afventer betaling</h2>
                    <ul class="list">
                        <li v-for="x in openPurchases('membership')" :key="x.id">
                            <div><strong>{{ x.name }}</strong> · {{ x.amount_fmt }}</div>
                            <div class="small muted">{{ x.people.join(', ') }}. Gælder fra betalingen<template v-if="x.valid_to"> til {{ dato(x.valid_to) }}</template>. Nye personer tilføjes, når der er betalt.</div>
                            <div class="row" style="margin-top:8px">
                                <RouterLink :to="`/betal/${x.invoice_id}`" class="btn small primary">Betal nu</RouterLink>
                                <button v-if="x.can_cancel && store.user.status !== 'pending'" class="btn small" @click="cancelPurchase(x)">Fortryd</button>
                            </div>
                        </li>
                    </ul>
                </div>

                <form v-if="store.user.status !== 'pending' && (shop.can_add_people || withoutMembership.length)" class="card stack" @submit.prevent="buyMembership">
                    <h2>Køb medlemskab</h2>
                    <p class="small muted" style="margin:0">Vælg hvem medlemskabet skal dække. Skal der tilføjes et barn eller en partner til husstanden, gør du det her. Har I allerede et medlemskab, kan I udvide det, så det dækker jer alle – med det samme eller fra næste sæson – eller købe et eget medlemskab til den nye person.</p>
                    <div v-if="withoutMembership.length">
                        <span class="lbl">Personer i husstanden uden medlemskab</span>
                        <label v-for="m in withoutMembership" :key="m.id" class="check"><input type="checkbox" :value="m.id" v-model="ms.member_ids"> {{ m.name }} <span class="muted small">({{ m.age }} år)</span></label>
                    </div>
                    <div v-if="shop.can_add_people">
                        <span class="lbl">Nye personer</span>
                        <div v-for="(n, i) in ms.new_members" :key="i" class="newp">
                            <div class="grid c2">
                                <label class="field"><span>Navn</span><input v-model="n.name" required></label>
                                <label class="field"><span>Fødselsdato</span><input v-model="n.birthdate" type="date" required></label>
                            </div>
                            <div class="grid c2">
                                <label class="field"><span>Egen e-mail (valgfri)</span><input v-model="n.email" type="email" autocapitalize="off"></label>
                                <label class="field"><span>Eget telefonnummer (valgfrit)</span><input v-model="n.phone" type="tel"></label>
                            </div>
                            <div class="row between"><label class="check small"><input type="checkbox" v-model="n.consent" required> Jeg giver som forælder/værge samtykke til, at klubben behandler oplysningerne.</label>
                                <button type="button" class="btn small ghost danger" @click="ms.new_members.splice(i, 1)">Fjern</button></div>
                        </div>
                        <p class="small muted" style="margin:0 0 8px">Skal personen selv kunne logge ind i appen og få notifikationer, skal vedkommende have sin egen e-mail eller sit eget telefonnummer – ikke dit. Ellers klarer du alt for personen.</p>
                        <button type="button" class="btn small" @click="ms.new_members.push({ name: '', birthdate: '', email: '', phone: '', consent: false }); track('familie', 'tilfoej_person')">+ Tilføj ny person</button>
                    </div>
                    <!-- Udvid husstandens medlemskab: alle på det nuværende + de nye, med det samme (betal differencen) eller fra næste sæson -->
                    <div v-if="msPeople.length && chg && (chg.now.length || chg.next.length)">
                        <span class="lbl">Udvid jeres medlemskab</span>
                        <p class="small muted" style="margin:0 0 8px">I har <strong>{{ chg.base.name }}</strong> ({{ chg.base.people.join(', ') }}). Vælg et medlemskab, der dækker jer alle.</p>
                        <div class="chips" style="margin-bottom:8px">
                            <button v-if="chg.now.length" type="button" :class="['chip', { on: ms.when === 'now' }]" @click="setWhen('now')">Med det samme</button>
                            <button v-if="chg.next.length" type="button" :class="['chip', { on: ms.when === 'next' }]" @click="setWhen('next')">Fra næste sæson ({{ dato(chg.next_from) }})</button>
                        </div>
                        <div class="pkgs">
                            <label v-for="o in chg[ms.when]" :key="o.package_id" :class="['pkg', { on: ms.change === o.package_id }]">
                                <input type="radio" :checked="ms.change === o.package_id" @change="ms.change = o.package_id; ms.package_id = null">
                                <span class="pbody">
                                    <span class="row between" style="flex-wrap:nowrap;align-items:flex-start;gap:10px"><strong>{{ o.name }}</strong><strong class="num">{{ o.amount_fmt }}</strong></span>
                                    <span class="small muted">{{ o.persons }} personer · {{ dato(o.valid_from) }} – {{ dato(o.valid_to) }}</span>
                                    <span v-if="o.credit" class="small muted">{{ o.price_fmt }} − {{ o.credit_fmt }} I allerede har betalt = {{ o.amount_fmt }}</span>
                                    <span v-if="o.capped" class="small ok-txt">Prisen er sat ned, så den ikke er højere end jeres enkeltkontingenter tilsammen.</span>
                                    <span v-if="ms.when === 'now' && o.also_next" class="small muted">Næste sæsons kontingent er allerede oprettet. Det rettes også til {{ o.name }}, når der er betalt.</span>
                                    <span v-if="ms.when === 'next' && chg.next_base && chg.next_base.status === 'open'" class="small muted">Erstatter det oprettede kontingent for næste sæson ({{ chg.next_base.name }}).</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="ms.when === 'next'" class="small muted" style="margin:6px 0 0">Jeres nuværende medlemskab er uændret resten af sæsonen. {{ msPeople.map(x => x.name || 'Den nye').join(', ') }} bliver først medlem fra {{ dato(chg.next_from) }}.</p>
                    </div>
                    <div v-if="msPeople.length">
                        <span class="lbl">{{ chg && (chg.now.length || chg.next.length) ? 'Eller et eget medlemskab til ' + msPeople.map(x => x.name || 'den nye').join(', ') : 'Vælg medlemskab' }}</span>
                        <PackageList :model-value="ms.package_id" @update:model-value="(v) => { ms.package_id = v; ms.change = null; }" :packages="shop.memberships" :fits="(x) => compositionError(x, msPeople, shop.categories)" :price-of="(x) => priceFor(x, msPeople, shop.categories, shop.singles)" />
                    </div>
                    <p v-if="msError" class="banner warn small" style="margin-bottom:0">{{ msError }}</p>
                    <button class="btn primary" :disabled="(!msPkg && !msChange) || !!msError || busy">{{ msChange ? (msChange.amount ? `Skift og betal ${msChange.amount_fmt}` : `Skift til ${msChange.name}`) : msPkg ? `Køb og betal ${kr(priceFor(msPkg, msPeople, shop.categories, shop.singles).price)}` : 'Vælg personer og medlemskab' }}</button>
                </form>
                <div v-if="store.user.status === 'pending'" class="banner warn">Betal først dit eget medlemskab. Derefter kan du købe medlemskab til andre i husstanden.</div>
            </template>
        </div>

        <div v-if="cur === 'beskeder'" class="card">
            <h2>Hvordan vil du have besked?</h2>
            <div class="stack">
                <label class="check"><input type="checkbox" v-model="f.notify_email" @change="saveProfile"> E-mail</label>
                <label class="check"><input type="checkbox" v-model="f.notify_push" @change="saveProfile"> Push-besked på telefonen</label>
                <p v-if="!IS_APP" class="small muted" style="margin:-4px 0 0 30px">Virker kun, når du har klubbens app på telefonen og er logget ind dér.</p>
            </div>
            <h2 style="margin-top:20px">Beskeder fra andre medlemmer</h2>
            <label class="check"><input type="checkbox" v-model="f.allow_messages" @change="saveProfile"> Andre medlemmer må skrive til mig</label>
            <p class="small muted" style="margin-top:4px">Slår du det fra, kan andre medlemmer ikke skrive til dig, og du kan selv kun skrive med trænere og bestyrelse. Trænere og bestyrelse kan altid skrive til dig om klubbens aktiviteter.</p>
            <h2 style="margin-top:20px">Hvilke kategorier vil du høre om?</h2>
            <p class="small muted">Fravælg kategorier, du ikke er interesseret i. Det gælder både nye aktiviteter og opslag. Du får stadig besked om aktiviteter, du er tilmeldt. Nedtonede kategorier får alle medlemmer besked om.</p>
            <label class="check" style="margin:8px 0"><input type="checkbox" v-model="f.notify_posts" @change="saveProfile"> Push-besked, når et medlem skriver et opslag om en kategori, jeg følger</label>
            <p v-if="f.notify_posts && !f.notify_push" class="small muted" style="margin:-4px 0 8px 30px">Kræver, at push-besked på telefonen er slået til ovenfor.</p>
            <ul class="list">
                <li v-for="c in cats" :key="c.id"><label :class="['check', { locked: c.mandatory }]" :title="c.mandatory ? 'Klubben sender altid besked om denne kategori' : null"><input type="checkbox" v-model="c.notify" :disabled="c.mandatory" @change="saveCats"><span class="tag"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</span><span v-if="c.mandatory" class="small muted">Kan ikke fravælges</span></label></li>
            </ul>
        </div>

        <Receipt v-if="cur === 'kvitteringer'" embedded />

        <div v-if="cur === 'regninger'">
            <div v-if="!invoices" class="spinner"></div>
            <div v-else class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="i in invoices" :key="i.id">
                        <div class="row between" style="flex-wrap:nowrap;align-items:flex-start">
                            <div><div><strong>{{ i.description }}</strong></div>
                                <div class="small muted">{{ i.status === 'paid' ? `Betalt ${dato(i.paid_at)} · ${i.method}` : `Forfald ${dato(i.due_on)}` }}<span v-if="i.refunded_fmt"> · refunderet {{ i.refunded_fmt }}</span></div></div>
                            <div style="text-align:right"><div class="num"><strong>{{ i.amount_fmt }}</strong></div><span :class="['tag', stCls(i.status)]">{{ i.status_label }}</span></div>
                        </div>
                        <RouterLink v-if="['open', 'overdue'].includes(i.status)" :to="`/betal/${i.id}`" class="btn small primary" style="margin-top:8px">Betal</RouterLink>
                    </li>
                    <li v-if="!invoices.length" class="muted">Ingen regninger.</li>
                </ul>
            </div>
        </div>

        <div v-if="cur === 'tilkob' && shop">
            <div v-if="shop.purchases.filter(x => x.kind === 'product').length" class="card">
                <h2>Mine tilkøb</h2>
                <ul class="list"><li v-for="o in shop.purchases.filter(x => x.kind === 'product')" :key="o.id" class="row between" style="flex-wrap:nowrap">
                    <span><strong>{{ o.name }}</strong><span v-if="o.resource"> · {{ o.resource }}</span> <span class="muted small" style="display:block">{{ o.people.join(', ') }}<span v-if="o.valid_to">, til {{ dato(o.valid_to) }}</span></span></span>
                    <span class="row" style="gap:6px;flex-wrap:nowrap">
                        <RouterLink v-if="['open', 'overdue'].includes(o.status)" :to="`/betal/${o.invoice_id}`" class="btn small primary">Betal</RouterLink>
                        <button v-if="o.can_cancel" class="btn small" @click="cancelPurchase(o)">Fortryd</button>
                        <span v-if="o.status === 'paid'" class="tag ok">Betalt</span>
                    </span>
                </li></ul>
            </div>
            <h3>Klubbens tilbud</h3>
            <p v-if="!shop.products.length" class="muted">Der er ingen tilkøb lige nu.</p>
            <div class="grid c2">
                <div v-for="x in shop.products" :key="x.id" class="card flat" style="margin:0">
                    <div class="row between" style="flex-wrap:nowrap;align-items:flex-start"><strong>{{ x.name }}</strong><span class="num" style="white-space:nowrap">{{ x.price_fmt }}</span></div>
                    <p class="small muted" style="margin:4px 0 8px">{{ x.description }} <span style="display:block">{{ x.composition_label }} · {{ x.valid_to ? `til ${dato(x.valid_to)}` : x.period_label }}<template v-if="x.requires_membership"> · kræver medlemskab</template></span></p>
                    <p v-if="x.needs_resource" class="small">{{ x.free_resources.length ? `${x.free_resources.length} ledige pladser` : 'Ingen ledige pladser' }}</p>
                    <button class="btn small" :disabled="x.needs_resource && !x.free_resources.length" @click="openBuy(x)">Vælg</button>
                </div>
            </div>
        </div>

        <div v-if="cur === 'data'" class="stack">
            <div class="card">
                <h2>Dine data</h2>
                <p>Du kan altid hente alle de oplysninger, klubben har om dig og din husstand: profil, roller, tilmeldinger og betalinger.</p>
                <button class="btn" @click="download('profile/export', 'mine-data.json')"><Icon name="download" style="width:18px" /> Hent mine data</button>
            </div>
            <div class="card">
                <h2>Roller i klubben</h2>
                <ul class="list"><li v-for="(r, i) in p.roles" :key="i" class="row between"><span>{{ r.label }}</span><span class="small muted">{{ dato(r.starts_on) }} – {{ r.ends_on ? dato(r.ends_on) : 'nu' }}</span></li>
                    <li v-if="!p.roles.length" class="muted">Medlem</li></ul>
            </div>
            <div v-if="!isMobile" class="card"><h2>Udseende</h2><ThemeSwitch /></div>
        </div>

        <!-- Eget login til en person i husstanden (betaleren): egen e-mail/telefon + midlertidig adgangskode -->
        <Modal v-if="lg" :title="`Login – ${lg.name}`" @close="lg = null">
            <div v-if="!lg.code" class="stack">
                <p class="small muted" style="margin:0">Skal {{ lg.first }} selv kunne logge ind i appen og få notifikationer, skal {{ lg.first }} have sin egen e-mail eller sit eget telefonnummer – ikke dit. Ellers klarer du tilmelding og betaling for {{ lg.first }}.</p>
                <label class="field"><span>Egen e-mail</span><input v-model="lg.email" type="email" autocapitalize="off" spellcheck="false"></label>
                <label class="field"><span>Eget telefonnummer</span><input v-model="lg.phone" type="tel"></label>
                <button class="btn block" :disabled="lg.busy" @click="saveLogin">Gem</button>
                <template v-if="lg.can_login">
                    <p class="small muted" style="margin:8px 0 0">{{ lg.has_password ? `${lg.first} har en adgangskode. Har ${lg.first} glemt den, kan du lave en ny midlertidig her.` : `Lav en midlertidig adgangskode, som ${lg.first} logger ind med første gang og derefter skifter.` }}</p>
                    <button class="btn primary block" :disabled="lg.busy" @click="makeLoginCode">Lav midlertidig adgangskode</button>
                </template>
            </div>
            <div v-else class="stack">
                <p style="margin:0">{{ lg.first }} logger ind med</p>
                <p style="margin:0"><strong>{{ lg.code.login }}</strong></p>
                <p style="margin:0">Adgangskode: <strong style="font-family:ui-monospace,monospace;font-size:1.25em;letter-spacing:.04em">{{ lg.code.password }}</strong></p>
                <p class="small muted" style="margin:0">Koden vises kun denne ene gang. {{ lg.first }} skal vælge sin egen adgangskode ved første login.</p>
                <button class="btn block" @click="lg = null">Luk</button>
            </div>
        </Modal>
        <Modal v-if="buy" :title="buy.name" @close="buy = null">
            <div class="stack">
                <div>
                    <span class="lbl">Hvem skal have det? <span class="muted">({{ buy.composition_label }})</span></span>
                    <label v-for="m in shop.household" :key="m.id" class="check" style="margin-top:6px"><input type="checkbox" :value="m.id" v-model="buyMembers"> {{ m.name }}<span v-if="buy.requires_membership && !m.membership" class="muted small"> (intet medlemskab)</span></label>
                </div>
                <label v-if="buy.needs_resource" class="field"><span>Plads</span><select v-model="buyRes"><option v-for="r in buy.free_resources" :key="r.id" :value="r.id">{{ r.label }}</option></select></label>
                <p v-if="buyError" class="banner warn small" style="margin-bottom:0">{{ buyError }}</p>
                <p style="margin:0"><strong>{{ buy.price_fmt }}</strong> <span class="muted">({{ buy.valid_to ? `${dato(buy.valid_from)} – ${dato(buy.valid_to)}` : buy.period_label }})</span></p>
                <button class="btn primary block" :disabled="!!buyError || busy" @click="order">Bestil og betal</button>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { openHelp } from '../help';
import HelpButton from '../components/HelpButton.vue';
import { track } from '../usage';
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { isMobile } from '../mobile';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import PackageList from '../components/PackageList.vue';
import NextSeasonEditor from '../components/NextSeasonEditor.vue';
import Receipt from './Receipt.vue';
import { compositionError, priceFor } from '../packages';
import ThemeSwitch from '../components/ThemeSwitch.vue';
import AppVersion from '../components/AppVersion.vue';
import { api, store, dato, kr, toast, download, roleLabel, IS_APP, logout as doLogout, openSite } from '../api';

const props = defineProps({ tab: String });
const router = useRouter();
const tabs = [
    { id: 'oplysninger', label: 'Oplysninger', title: 'Oplysninger', icon: 'user', hint: 'Navn, kontakt og nødkontakt' },
    { id: 'familie', label: 'Familie', title: 'Familie og medlemskab', icon: 'users', hint: 'Husstand og køb af medlemskab' },
    { id: 'beskeder', label: 'Beskeder', title: 'Beskeder og notifikationer', icon: 'bell', hint: 'E-mail, push og hvilke aktiviteter', group: 2 },
    { id: 'regninger', label: 'Regninger', title: 'Regninger', icon: 'money', hint: 'Betalte og ubetalte regninger' },
    { id: 'kvitteringer', label: 'Kvitteringer', title: 'Kvitteringer', icon: 'receipt', hint: 'Få penge tilbage for udlæg' },
    { id: 'tilkob', label: 'Tilkøb', title: 'Tilkøb', icon: 'bag', hint: 'Pladser, leje og andre tilkøb' },
    { id: 'data', label: 'Mine data', title: 'Mine data', icon: 'file', hint: 'Roller og hent dine data', group: 2 },
];
// På mobil er /profil selve menuen; på computer åbner den første fane
const cur = computed(() => props.tab || (isMobile.value ? null : 'oplysninger'));
const curTab = computed(() => tabs.find(t => t.id === cur.value) || tabs[0]);
const menuGroups = computed(() => {
    const c = p.value?.counts || {};
    const m = p.value?.membership, up = p.value?.upcoming;
    const extra = {
        familie: { hint: m ? m.name + (m.valid_to ? ' · til ' + dato(m.valid_to) : '') : up ? up.name + ' · fra ' + dato(up.valid_from) : 'Intet gyldigt medlemskab', ...(!m && !up ? { badge: 'Ikke medlem', badgeCls: 'bad' } : {}) },
        regninger: c.open_invoices ? { badge: c.open_invoices === 1 ? '1 åben' : `${c.open_invoices} åbne`, badgeCls: 'warn' } : {},
        kvitteringer: c.pending_claims ? { badge: `${c.pending_claims} afventer`, badgeCls: 'info' } : {},
    };
    const rows = tabs.map(t => ({ ...t, ...(extra[t.id] || {}) }));
    return [rows.filter(t => !t.group), rows.filter(t => t.group)];
});
const stCls = (s) => ({ paid: 'ok', open: 'info', overdue: 'bad', refunded: '', cancelled: '' })[s];

const p = ref(null); const f = reactive({}); const cats = ref([]); const invoices = ref(null); const shop = ref(null); const busy = ref(false);
const buy = ref(null); const buyMembers = ref([]); const buyRes = ref(null);
const ms = reactive({ member_ids: [], new_members: [], package_id: null, change: null, when: 'now' });
// Skift af husstandens medlemskab: muligheder hentes fra serveren, når personerne ændres (kræver fødselsdatoer)
const chg = ref(null);
const nextEdit = ref(false);
// Eget login til en person i husstanden
const lg = ref(null);
function openLogin(m) { lg.value = { id: m.id, name: m.name, first: m.name.split(' ')[0], email: m.email || '', phone: m.phone || '', can_login: m.can_login, has_password: m.has_password, busy: false, code: null }; }
async function saveLogin() {
    lg.value.busy = true;
    try {
        const r = await api(`profile/household/${lg.value.id}/login`, { method: 'PUT', body: { email: lg.value.email.trim() || null, phone: lg.value.phone.trim() || null } });
        lg.value.can_login = r.can_login; toast('Gemt'); loadShop();
    } catch (e) { toast(e.message, 'error'); } finally { lg.value.busy = false; }
}
async function makeLoginCode() {
    lg.value.busy = true;
    try { lg.value.code = await api(`profile/household/${lg.value.id}/temp-password`, { method: 'POST' }); loadShop(); }
    catch (e) { toast(e.message, 'error'); } finally { if (lg.value) lg.value.busy = false; }
}
const nextText = computed(() => {
    const n = shop.value?.next_plan; if (!n) return '';
    const on = n.people.filter(p => p.renews); const stop = n.people.filter(p => !p.renews).map(p => p.name.split(' ')[0]);
    const what = n.split ? on.map(p => `${p.own?.name} (${p.name.split(' ')[0]})`).join(', ') : n.next_as.name;
    return what + (stop.length ? ` – uden ${stop.join(' og ')}` : '');
});
const msChange = computed(() => chg.value && ms.change ? chg.value[ms.when].find(o => o.package_id === ms.change) || null : null);
function setWhen(w) { ms.when = w; if (!chg.value?.[w].some(o => o.package_id === ms.change)) ms.change = null; }
let chgT, chgSeq = 0;
watch(() => [ms.member_ids.slice(), ms.new_members.map(n => n.birthdate)], () => {
    clearTimeout(chgT);
    if (!ms.member_ids.length && !ms.new_members.length) { chg.value = null; ms.change = null; return; }
    if (ms.new_members.some(n => !n.birthdate)) return;
    chgT = setTimeout(async () => {
        const seq = ++chgSeq;
        try {
            const r = await api('profile/membership-change/options', { method: 'POST', body: { member_ids: ms.member_ids, new_members: ms.new_members.map(n => ({ name: n.name || null, birthdate: n.birthdate })) } });
            if (seq !== chgSeq) return;
            chg.value = r.options;
            if (chg.value && !chg.value[ms.when].length) ms.when = chg.value.now.length ? 'now' : 'next';
            if (ms.change && !chg.value?.[ms.when].some(o => o.package_id === ms.change)) ms.change = null;
        } catch { if (seq === chgSeq) chg.value = null; }
    }, 300);
}, { deep: true });

const withoutMembership = computed(() => shop.value ? shop.value.household.filter(m => !m.membership && !m.upcoming && !openPurchases('membership').some(x => x.people.includes(m.name))) : []);
const openPurchases = (kind) => shop.value ? shop.value.purchases.filter(x => x.kind === kind && ['open', 'overdue'].includes(x.status)) : [];
const msPeople = computed(() => [...shop.value.household.filter(m => ms.member_ids.includes(m.id)), ...ms.new_members]);
const msPkg = computed(() => shop.value?.memberships.find(x => x.id === ms.package_id) || null);
const msError = computed(() => !msChange.value && msPkg.value && msPeople.value.length ? compositionError(msPkg.value, msPeople.value, shop.value.categories) : null);
const buyError = computed(() => {
    if (!buy.value) return null;
    const people = shop.value.household.filter(m => buyMembers.value.includes(m.id));
    const noMember = buy.value.requires_membership ? people.find(m => !m.membership) : null;
    return noMember ? `${buy.value.name} kræver medlemskab, og det har ${noMember.name} ikke.` : compositionError(buy.value, people, shop.value.categories);
});

async function load() { p.value = await api('profile'); Object.assign(f, p.value.profile); }
async function saveProfile() {
    try { const r = await api('profile', { method: 'PUT', body: f }); Object.assign(f, r); p.value.profile = r; toast('Gemt'); }
    catch (e) { toast(e.message, 'error'); }
}
async function saveCats() { await api('profile/categories', { method: 'PUT', body: { items: cats.value } }); toast('Gemt'); }
async function loadShop() { shop.value = await api('profile/packages'); }
async function buyMembership() {
    busy.value = true;
    const change = msChange.value;
    try {
        const body = { member_ids: ms.member_ids, new_members: ms.new_members.map(n => ({ ...n, email: n.email || null })) };
        const r = change
            ? await api('profile/membership-change', { method: 'POST', body: { ...body, package_id: change.package_id, when: ms.when } })
            : await api('profile/purchases', { method: 'POST', body: { ...body, package_id: ms.package_id } });
        track('medlemskab', 'ok', { personer: ms.member_ids.length + ms.new_members.length, nye: ms.new_members.length, betaling: !r.paid, ...(change ? { skift: ms.when } : {}) });
        Object.assign(ms, { member_ids: [], new_members: [], package_id: null, change: null }); chg.value = null;
        if (!r.paid) return router.push(`/betal/${r.invoice_id}`);
        toast(change ? 'Medlemskabet er skiftet' : 'Medlemskabet er oprettet'); loadShop(); load();
    } catch (e) { toast(e.message, 'error'); track('medlemskab', 'fejl', { status: e.status }); } finally { busy.value = false; }
}
async function cancelPurchase(x) {
    if (!confirm(`Fortryd ${x.name}? Regningen annulleres.`)) return;
    try { await api(`profile/purchases/${x.id}/cancel`, { method: 'POST' }); toast('Købet er fortrudt'); loadShop(); } catch (e) { toast(e.message, 'error'); }
}
function openBuy(x) { track('tilkob', 'start', { slags: x.kind }); buy.value = x; buyMembers.value = x.total_max === 1 ? [p.value.profile.id] : []; buyRes.value = x.free_resources?.[0]?.id; }
async function order() {
    busy.value = true;
    try { const r = await api('profile/purchases', { method: 'POST', body: { package_id: buy.value.id, member_ids: buyMembers.value, resource_id: buyRes.value } }); track('tilkob', 'ok'); router.push(`/betal/${r.invoice_id}`); }
    catch (e) { toast(e.message, 'error'); track('tilkob', 'fejl', { status: e.status }); } finally { busy.value = false; }
}
async function logout() { await doLogout(); router.push('/login'); }

const pw = reactive({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref(''); const pwBusy = ref(false);
async function changePassword() {
    pwError.value = '';
    if (pw.password !== pw.password_confirmation) { pwError.value = 'De to nye adgangskoder er ikke ens.'; track('adgangskode', 'skift_mangler', { felt: 'confirmed' }); return; }
    pwBusy.value = true;
    try {
        toast((await api('profile/password', { method: 'PUT', body: pw })).message);
        Object.assign(pw, { current_password: '', password: '', password_confirmation: '' });
        track('adgangskode', 'skift_ok');
    } catch (e) { pwError.value = e.message; track('adgangskode', 'skift_fejl', { status: e.status }); } finally { pwBusy.value = false; }
}

async function onTab(t) {
    if (t === 'beskeder' && !cats.value.length) cats.value = (await api('profile/categories')).sort((a, b) => b.mandatory - a.mandatory); // dem, der ikke kan fravælges, først
    if (t === 'regninger') invoices.value = await api('profile/invoices');
    if (t === 'familie' || t === 'tilkob') await loadShop();
}
watch(cur, (t) => { onTab(t); if (!t) load(); }); // tilbage til menuen: opdatér tællerne
onMounted(async () => { await load(); onTab(cur.value); });
</script>

<style scoped>
.check.locked { opacity: .5; cursor: default; }
.check.locked input { cursor: default; }
.lbl { display: block; font-size: .85rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.back { display: inline-block; margin-bottom: 6px; }
/* Postnr. og By på samme linje (fødselsdato under) på mobil */
@media (max-width: 699.98px) { .addr { grid-template-columns: 1fr 2fr; } .addr > :last-child { grid-column: 1 / -1; } }
@media (max-width: 899.98px) { .save-btn { position: sticky; bottom: var(--sticky-bottom); z-index: 5; box-shadow: 0 4px 16px rgba(0,0,0,.18); } }
.menu { padding: 2px 16px; }
.menu .list > li { padding: 0; }
.mrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; }
button.mrow { width: 100%; background: none; border: 0; font: inherit; text-align: left; cursor: pointer; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mtext .small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
.pkgs { display: grid; gap: 8px; }
.pkg { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); cursor: pointer; }
.pkg.on { border-color: var(--accent); background: var(--accent-soft); }
.pkg input { width: 20px; height: 20px; min-height: 0; margin: 2px 0 0; padding: 0; flex: 0 0 auto; accent-color: var(--accent); }
.pbody { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.ok-txt { color: var(--ok); }
.newp { border: 1px solid var(--line); border-radius: 12px; padding: 10px; margin-bottom: 8px; display: grid; gap: 8px; }
</style>
