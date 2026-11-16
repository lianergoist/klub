<?php

/**
 * Hjemmesidens indhold, som det så ud ved konverteringen fra Squarespace (16/11-2026).
 * Bruges kun af migrationen, der opretter site_pages – derefter ligger indholdet i databasen.
 * Bloktyper og felter: se App\Support\Site.
 */
$img = fn (string $name) => '/hjemmeside/billeder/'.$name.'.webp';

return [
    [
        'slug' => '',
        'title' => 'Forside',
        'seo_title' => 'SurfHorsens – Windsurfing, Wingfoil & SUP i Horsens Fjord · Så meget AlÅha',
        'description' => 'Mærk suset med SurfHorsens! Vi dyrker vandsport med masser af AlÅha-spirit ved Husodde Strand. Uanset om du er til windsurfing, wingfoil eller SUP, har vi fællesskabet, grejet og kurserne til dig.',
        'admin_only' => false,
        'blocks' => [
            ['id' => 'f1', 'type' => 'hero', 'height' => 'screen', 'image' => $img('hav-aften'), 'kicker' => "Vild med vind\nSå meget ALÅHA", 'heading' => 'SURF HORSENS', 'logo' => 'farve',
                'button' => ['label' => 'Mere ALÅHA', 'link' => 'next']],
            ['id' => 'f2', 'type' => 'columns', 'layout' => 'two', 'tone' => 'pink', 'cols' => [
                [['t' => 'image', 'src' => $img('alaha-wind'), 'alt' => 'SurfHorsens – Sist’r and brot’rs in the wind, så meget ALÅHA', 'plain' => true]],
                [['t' => 'text', 'html' => '<h2>Fart, frihed og fællesskab<br>i fjorden</h2><h3>og så meget ALÅHA!</h3><p>Oplev fart, frihed og fællesskab på vandet.<br>Kurser, events og surf for alle niveauer – ved Husodde Strand.<br>Prøv windsurf, SUP eller wingfoil hos SurfHorsens.</p><p>Vi sørger for udstyr, undervisning og gode oplevelser.</p>']],
            ]],
            ['id' => 'f3', 'type' => 'columns', 'layout' => 'two', 'align' => 'center', 'cols' => [
                [['t' => 'image', 'src' => $img('born-paa-stranden'), 'alt' => 'To børn på stranden, den ene bærer den anden på ryggen, begge smilende i SurfHorsens-trøjer.']],
                [['t' => 'text', 'html' => '<h3>Frihed og fællesskab</h3><p>Vi sejler ikke lårene ag hinanden men vi har et magisk fælleskab. Vil du sejle selv eller flyve i vinden med Wind-seasters and windbrothers</p>'],
                    ['t' => 'button', 'label' => 'ALÅHA Ja tak', 'link' => 'page:/priser-og-medlemskab']],
            ]],
            ['id' => 'f4', 'type' => 'columns', 'layout' => 'two', 'align' => 'center', 'cols' => [
                [['t' => 'text', 'html' => '<h3>CAMP</h3><p>Alåha hold din uge 27 fri til camp uge</p><p>så meget ALÅHA i en uge med magiske mennesker i Ringkøbing</p>'],
                    ['t' => 'button', 'label' => 'Tag mig med!', 'link' => 'page:/kalender']],
                [['t' => 'image', 'src' => $img('camp'), 'alt' => 'SurfHorsens surfcamp 2026 i uge 27']],
            ]],
            ['id' => 'f5', 'type' => 'columns', 'layout' => 'two', 'align' => 'center', 'cols' => [
                [['t' => 'image', 'src' => $img('instruktor'), 'alt' => 'Instruktør fra SurfHorsens på et board i vandet', 'link' => 'page:/priser-og-medlemskab'],
                    ['t' => 'text', 'html' => '<h3>Undervisning<br>af vores Wind-strucktors</h3><p>Lær at windsurfe, flyv i vinden, flix i fælleskabet, lav de vildeste jibes med så meget <strong>ALÅHA</strong></p>']],
                [['t' => 'image', 'src' => $img('windsurfere'), 'alt' => 'To windsurfere i fart på fjorden', 'link' => 'page:/priser-og-medlemskab'],
                    ['t' => 'button', 'label' => 'ALÅHA – lær mig at jibe, hvad end det er', 'link' => 'page:/priser-og-medlemskab']],
            ]],
            ['id' => 'f6', 'type' => 'columns', 'layout' => 'one', 'bg' => 'orange', 'align' => 'center', 'cols' => [
                [['t' => 'text', 'html' => '<h2>Meld dig ind</h2><p>Meld dig ind i dag og bliv en del af vores magiske AlÅha vibe</p>'],
                    ['t' => 'button', 'label' => 'Meld dig ind', 'link' => 'signup']],
            ]],
        ],
    ],
    [
        'slug' => 'priser-og-medlemskab',
        'title' => 'Priser og medlemskab',
        'seo_title' => 'Priser og medlemskab · SurfHorsens',
        'description' => 'Opdag priser og medlemsmuligheder hos SurfHorsens. Få info om kurser, medlemskaber og udstyrsleje for vandsportsentusiaster i Horsens.',
        'admin_only' => false,
        'blocks' => [
            ['id' => 'p1', 'type' => 'hero', 'height' => 'screen', 'image' => $img('windsurfere'), 'heading' => 'Hva’ koster det’?', 'heading_color' => 'orange',
                'button' => ['label' => 'Se priserne', 'link' => 'next']],
            ['id' => 'p2', 'type' => 'columns', 'layout' => 'one', 'bg' => 'orange', 'align' => 'center', 'cols' => [
                [['t' => 'text', 'html' => '<h3>Klub træning i sæsonen hver søndag 10-13 og onsdage 17-19</h3>'],
                    ['t' => 'button', 'label' => 'Vær med', 'link' => 'block:p7']],
            ]],
            ['id' => 'p3', 'type' => 'columns', 'layout' => 'two', 'cols' => [
                [['t' => 'text', 'html' => '<h2>Action – Eventyr – Natur !</h2>']],
                [['t' => 'text', 'html' => '<p>Udfordr dig selv og oplev vandsport i unikke omgivelser hos SurfHorsens.</p><p>Uanset om du kommer som nybegynder, med vennerne eller som virksomhed, skaber vi en oplevelse, du ikke glemmer.</p><p><strong>Kurser, polterabend eller firmaarrangement?</strong><br>We got you covered!</p>']],
            ]],
            ['id' => 'p4', 'type' => 'card', 'image' => $img('windsurfskole'), 'alt' => 'En gruppe på stranden i SurfHorsens-kapper lytter til en instruktør ved vandkanten med windsurfudstyr.',
                'label' => 'Events og kursus', 'heading' => 'Windsurfing eller Wing foil begynderkursus',
                'html' => '<p>Lær windsurfing ved at tage et intro -eller begynderkursus. Hvis du starter på et introkursus kan du fortsætte på begynderkurset, så modregner vi det indbetalte beløb. Kurser oprettes efter behov.</p><p>Windsurfing Introkursus</p><p>3 timer – 1 dag</p><p>Pris pr. deltager: kr. 499,-</p><p>Windsurfing begynderkursus</p><p>9 timer – 3 dage</p><p>Pris pr. deltager: kr. 1.199,-</p><p>Har du spørgsmål eller vil du tilmelde dig et kursus. Så kontakt os <a href="/kontakt">her</a>.</p>'],
            ['id' => 'p5', 'type' => 'card', 'image' => $img('smil-i-vandet'), 'alt' => 'En smilende kvinde i SurfHorsens-trøje står i vandet og viser tommelfingeren op, med windsurfere i baggrunden.',
                'label' => '1 dags kursus', 'heading' => 'Action – Eventyr – Natur !',
                'html' => '<p>Action – Eventyr – Natur !</p><p>Udfordre dig selv og prøv en fed vandsport hos SurfHorsens.</p><p>Kursus, polterabend eller firmaarrangement? We got you covered!</p><p>Kurser inkluderer: alt udstyr, våddragt, surfsko og svømmevest</p><p>Du skal selv medbringe håndklæde, badetøj, solcreme.</p><p>Du skal kunne svømme.</p>'],
            ['id' => 'p6', 'type' => 'card', 'image' => $img('sup-kursus'), 'alt' => 'En gruppe på stranden med blå SUP-boards lytter til en instruktør; bro og vand i baggrunden.',
                'label' => 'Kursus event SUP', 'heading' => 'Stand Up Paddle kursus',
                'html' => '<p>SUP eller Stand Up Paddle er noget hele familien kan være med til. På dette begynderkursus lærer du basale færdigheder og vil være i stand til at finde det rigtige SUP-spot, gå ud i de rigtige vejrforhold og paddle den rigtige vej. Kurser oprettes efter behov.</p><p>Kurset varer 3 – 4 timer.</p><p>Pris: <span data-pris="2">kr. 499,-</span></p><p>Har du spørgsmål eller vil du tilmelde dig et kursus. Så kontakt os <a href="/kontakt">her</a>.</p>'],
            ['id' => 'p7', 'type' => 'card', 'image' => $img('born-paa-stranden'), 'alt' => 'To børn i SurfHorsens-trøjer nyder tiden på stranden, med en bro og en person på et paddleboard i baggrunden.',
                'heading' => 'Kontingenter & priser', 'prices' => true, 'button' => ['label' => 'Meld dig ind', 'link' => 'signup']],
            ['id' => 'p8', 'type' => 'columns', 'layout' => 'one', 'cols' => [
                [['t' => 'text', 'html' => '<h4>Hvilken periode dækker kontingent og hyldelejen?</h4><p>1. maj til 30. april.</p><h4>Hvor skal pengene indbetales?</h4><p>Overføres til konto 1551-0002393247 (Danske Bank) med oplysning om navn. Eller igennem app’en MinForening hvor man har tilknyttet sin MobilePay.</p><h4>Hvordan får man en hylde i medlemscontaineren til sit udstyr?</h4><p>Man kontakter formanden (p.t. Mathijs Broersma) eller en instruktør, som tildeler hylden (hvor der er en ledig).</p><ul><li>Du betaler for hyldelejen igennem app’en MinForening.</li><li>Du betaler et depositum for en nøgle til containeren, p.t. kr. 100,-</li><li>Sætter et skilt med tydeligt navn på hylden</li><li>Sat navn på så meget af dit grej som muligt, som din sejlpose, bom, taske m.m. Du kan fx bruge en Dymo label printer.</li></ul>']],
            ]],
            ['id' => 'p9', 'type' => 'faq', 'label' => 'Svar på et par spørgsmål', 'items' => [
                ['q' => 'Hvordan bliver man medlem af SurfHorsens?', 'a' => '<p>Man sender en mail til kassereren (p.t. Jacob Strunge, mail), oplyser sit fulde navn, adresse, fødselsdato, mailadresse og telefonnummer og skriver, at man ønsker at blive medlem af klubben.</p><p>Man betaler det vedtagne årskontingent og evt. for udstyrsleje og hyldeplads.</p>'],
                ['q' => 'Hvor skal pengene indbetales?', 'a' => '<p>Overføres til konto 1551-0002393247 (Danske Bank) med oplysning om navn. Eller igennem app’en MinForening hvor man har tilknyttet sin MobilePay.</p>'],
                ['q' => 'Hvilken periode dækker kontingent og hyldelejen?', 'a' => '<p>1. maj til 30. april.</p>'],
                ['q' => 'Hvordan får man en hylde i medlemscontaineren til sit udstyr?', 'a' => '<p>Man kontakter formanden (p.t. Mathijs Broersma) eller en instruktør, som tildeler hylden (hvor der er en ledig).</p><ul><li>Du betaler for hyldelejen igennem app’en MinForening.</li><li>Du betaler et depositum for en nøgle til containeren, p.t. kr. 100,-</li><li>Sætter et skilt med tydeligt navn på hylden</li><li>Sat navn på så meget af dit grej som muligt, som din sejlpose, bom, taske m.m. Du kan fx bruge en Dymo label printer.</li></ul>'],
            ]],
        ],
    ],
    [
        'slug' => 'kalender',
        'title' => 'Kalender',
        'seo_title' => 'Det sker i SurfHorsens · Kalender',
        'description' => 'Det sker i SurfHorsens: events, surfcamps, klubture, kurser i windsurf, wingfoil og SUP.',
        'admin_only' => true,
        'blocks' => [
            ['id' => 'k1', 'type' => 'hero', 'height' => 'medium', 'image' => $img('hav-aften'), 'heading' => 'Det sker', 'heading_size' => 'h2', 'logo' => 'hvid'],
            ['id' => 'k2', 'type' => 'calendar', 'images' => array_map($img, ['born-paa-stranden', 'begivenhed-1', 'begivenhed-2', 'dronefoto', 'windsurfere', 'gruppe-paa-stranden', 'windsurfere-horsens'])],
        ],
    ],
    [
        'slug' => 'read-me',
        'title' => 'Så meget AlÅha',
        'seo_title' => 'Så meget AlÅha · SurfHorsens',
        'description' => 'Deltag i surffællesskabet i Horsens med windsurf, wingfoil og SUP. Oplev frihed, fællesskab og naturoplevelser ved Horsens Fjord.',
        'admin_only' => false,
        'blocks' => [
            ['id' => 'r1', 'type' => 'hero', 'height' => 'medium', 'image' => $img('instruktor'), 'kicker' => 'Surf Horsens', 'heading' => 'Så Meget AlÅha'],
            ['id' => 'r2', 'type' => 'columns', 'layout' => 'one', 'cols' => [
                [['t' => 'text', 'html' => '<h2>Hvorfor siger i ALÅHA? 🌺🌊</h2><h3>Og hvorfor laver i det der håndtegn, som om jeg skal ringe 🤙🏼?</h3><p>Fordi AlÅha er mere end et ord.<br>Det er en følelse. En energi. En måde at være i verden på. Det er en magisk følelse af sammenhold, flyve i vinden og naturen. Det er respekt for hinanden, væsner og natur.</p><p>Aloha kommer fra Hawaii –<br>men hos os har det fået saltvand i håret og rødder i Horsens.</p><p>ALÅHA er vores Hååååårsensisnake version. Vores bølge.</p><p>Det betyder hej. Det betyder farvel. Det betyder Surf vibe, magi. Det betyder kærlighed. Det er en respekt for hinanden og for naturen.</p><p>Men vigtigst af alt – det er noget, du mærker.<br>ALÅHA er… At møde hinanden og naturen med åbne hjerter og et glimt af noget magisk.✨</p><p>At være lige her med fødderne i sandet, flyvende på vandet og vinden i kroppen.<br>At give lidt af sig selv, bare fordi du kan.</p><p>På vandet giver det mening.</p><p>Vi jubler, hujer, hepper. Smiler, og hjælper hinanden.<br>AlÅha er ikke bare noget, vi siger. Det er noget, vi er. En bølge. En vind. Et sammenhold. En lethed. En respekt. En stemning. En måde at være sammen på. 🌊</p><p><strong>Velkommen i SurfHorsens</strong><br><strong>Så Meget AlÅha</strong></p>']],
            ]],
            ['id' => 'r3', 'type' => 'columns', 'layout' => 'one', 'cols' => [
                [['t' => 'text', 'html' => '<h3>ALÅHA – velkommen til SURFHorsens</h3><p>Vind og saltvand i håret. Flyvende frihed i kroppen.<br>SURFHorsens er et levende fællesskab ved Horsens Fjord –<br>for dig der vil på vandet, mærke naturen og være en del af noget ægte.</p><p>Windsurf. Wingfoil. SUP.</p><p>Begyndere og erfarne – alle er velkomne.</p><p><strong>Kom som du er. Gå på vandet. Bliv hængende.</strong></p><p><strong>ALÅHA – det starter her</strong></p><p>Her starter det med vinden. Med vandet. Og med følelsen af frihed.</p><p>SURFHorsens er ikke bare en klub – det er et fællesskab.<br>Et sted hvor hverdagen slipper, og hvor vi mødes omkring det, vi elsker:<br><strong>vand, bevægelse og den rå natur ved Horsens Fjord.</strong></p><p>⸻</p><p><strong>Et sted du lander – og hører til</strong></p><p>Midt ved Husodde Strand i Stensballe finder du vores base.<br>To containere fyldt med boards, sejl og historier –<br>og et miljø, hvor alle er velkomne.</p><p>Her er plads til:</p><ul><li>Dig der er helt ny</li><li>Dig der jagter næste niveau</li><li>Og dig der bare vil være tæt på vandet</li></ul><p>Vi har været her i over 30 år.<br>Ikke fordi vi skal – men fordi vi ikke kan lade være.</p><p>⸻</p><p><strong>Vand, vind og vilde grin</strong></p><p>Hos SURFHorsens dyrker vi det, der giver liv:</p><ul><li><strong>Windsurf / Windsurf Foil</strong></li><li><strong>Wing Foil</strong></li><li><strong>Stand Up Paddle (SUP)</strong></li></ul><p>Horsens Fjord er et af Østjyllands bedste spots – Vind vand og gode forhold og masser af plads til at lege, lære og udvikle sig.</p><p>A Jibe a day keeps the doctor away</p><p>⸻</p><p><strong>Fællesskab med saltvand i blodet</strong></p><p>Vi er en frivillig forening – men det, der binder os sammen, er noget helt andet.</p><p>Det er så meget ALÅHA:</p><ul><li>En kop kaffe i våddragt</li><li>En high-five og jubel i vandet</li><li>En hjælpsom hånd, når vinden driller</li></ul><p>Her hjælper vi hinanden.<br>Her deler vi oplevelserne.<br>Her er du aldrig alene på vandet.</p><p>⸻</p><p><strong>Mere end bare en sæson</strong></p><p>Hos os lever livet ved vandet hele året.</p><p>Som medlem får du mulighed for:</p><ul><li>Opbevaring af dit eget udstyr</li><li>Leje af udstyr</li><li>Et stærkt lokalt netværk</li></ul><p>⸻</p><p><strong>Kom som du er</strong></p><p>Du behøver ikke være ekspert.<br>Du behøver ikke have det rigtige gear.<br>Du skal bare have lyst.</p><p><strong>Lyst til at prøve.</strong><br><strong>Lyst til at være en del af noget.</strong><br><strong>Lyst til at mærke vinden.</strong></p><p><strong>ALÅHA!</strong></p>']],
            ]],
        ],
    ],
    [
        'slug' => 'kontakt',
        'title' => 'Kontakt',
        'seo_title' => 'Kontakt SurfHorsens',
        'description' => 'Kontakt SurfHorsens for medlemskab, priser og eventinformation ved Husodde Strand i Horsens.',
        'admin_only' => false,
        'blocks' => [
            ['id' => 'c1', 'type' => 'hero', 'height' => 'medium', 'image' => $img('smil-i-vandet'), 'heading' => 'Kontakt SURF Horsens', 'logo' => 'hvid'],
            ['id' => 'c2', 'type' => 'columns', 'layout' => 'one', 'cols' => [
                [['t' => 'text', 'html' => '<h2>Kontakt os i dag</h2>'], ['t' => 'rule']],
            ]],
            ['id' => 'c3', 'type' => 'columns', 'layout' => 'two', 'cols' => [
                [['t' => 'box', 'html' => '<h4>Kontakt os her angående spørgsmål om windsurfing og wingfoil</h4>']],
                [['t' => 'box', 'html' => '<h4>Kontakt os her angående spørgsmål om SUP (Stand up paddle)</h4>']],
            ]],
            ['id' => 'c4', 'type' => 'columns', 'layout' => 'two', 'cols' => [
                [['t' => 'text', 'html' => '<h2>SurfHorsens</h2><p>SurfHorsens holder til ved Husodde strand, i Horsens.</p><h4>SurfHorsens ’lokaler’</h4><p>Mobil nr. til træner Per Engholm:<br>51232771</p><p>Husoddevej 85<br>8700 Horsens<br>Danmark</p><p>Kasserer: Jacob Strunge<br>Gl. Næssevej 38, 8700 Horsens<br>Mobil: 22 47 17 22</p>']],
                [['t' => 'map', 'address' => 'Husoddevej 85, 8700 Horsens']],
            ]],
        ],
    ],
];
