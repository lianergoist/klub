<!--
Hjælpetekst til klubsystemet. Bruges af hjælpe-knapperne (?) og hjælpens søgning.

Format:
  ## Overskrift {#id kræver:<rettighed> side:/sti,/sti/:id}
  - #id er fast og bruges af <HelpButton topic="id" /> – omdøb den ikke.
  - kræver: vises kun for dem, der har rettigheden (samme nøgler som menuen: portal, equipment,
    members_view, finance_read, settings, activities_manage). Uden kræver: vises for alle medlemmer.
  - side: hvilke sider afsnittet hører til (ruteskabeloner fra app.js). Vises øverst i hjælpen på de sider.
Skriv kort, i du-form, og brug de samme ord som på knapperne.
-->

# Kom godt i gang

## Sådan er systemet bygget op {#overblik side:/aktiviteter,/mere}
Menuen har fire faste punkter: **Aktiviteter** (træninger, kurser og arrangementer), **Opslag** (klubbens opslagstavle), **Indbakke** (notifikationer og beskeder) og **Profil** (dine oplysninger, familie, regninger og indstillinger).

Har du en rolle i klubben, fx træner eller kasserer, får du også adgang til værktøjer som **Trænerportal**, **Udstyr**, **Medlemmer**, **Økonomi** og **Indstillinger**. På mobil ligger de under **Mere**, på computer i menuen til venstre.

## Login og adgangskode {#login side:/login,/glemt-adgangskode,/nulstil-adgangskode}
Du logger ind med din e-mail og adgangskode.

Har du glemt adgangskoden, trykker du **Glemt adgangskode?** på login-siden og skriver din e-mail. Du får en mail med et link, hvor du vælger en ny adgangskode. Linket virker i 60 minutter og kun én gang. Kommer mailen ikke, så kig i spam-mappen, eller kontakt bestyrelsen.

Er du ikke medlem endnu, trykker du **Meld dig ind** på login-siden.

## Bliv medlem {#indmeldelse side:/bliv-medlem}
Ved indmeldelse bliver du først spurgt, om andre i husstanden også skal være medlemmer. Derefter vises de medlemskaber, der passer, med det billigste først.

Du betaler med det samme med MobilePay, og når betalingen er gennemført, er du aktivt medlem. Indtil da står du som "afventer betaling".

Har du tidligere betalt for en prøvetræning eller et SUP-kursus med samme e-mail inden for 12 måneder, trækkes beløbet fra kontingentet.

# Aktiviteter

## Find aktiviteter {#aktiviteter side:/aktiviteter}
Under **Aktiviteter** ser du tre lister: **Kommende**, **Tilmeldt** (det, du har meldt dig til) og **Deltaget** (det, træneren har registreret, at du mødte op til).

Du kan filtrere på kategori, fx kun SUP eller wingfoil, og trykke **Gem som standard**, så filteret huskes.

## Tilmeld og afmeld {#tilmelding side:/aktiviteter/:id}
Åbn aktiviteten og tryk **Tilmeld mig**. Koster aktiviteten noget, sendes du videre til betaling, og du er først tilmeldt, når betalingen er gennemført.

Du afmelder dig på samme side med **Afmeld mig**. Nogle aktiviteter har en frist for afmelding, fx 24 timer før start. Efter svarfristen kan du ikke tilmelde dig uden trænerens accept.

Kan du ikke tilmelde dig, skyldes det oftest, at dit medlemskab ikke er betalt eller ikke gælder endnu, at aktiviteten er fuld, eller at svarfristen er overskredet.

## Tilmeld børn og familie {#tilmelding-familie side:/aktiviteter/:id}
Er du betaler for en husstand, kan du tilmelde dine børn og andre i husstanden med **Tilmeld familiemedlemmer** på aktivitetens side. Du kan tilmelde flere på én gang.

## Aflyste aktiviteter {#aflysning side:/aktiviteter/:id}
Bliver en aktivitet aflyst, får alle tilmeldte besked, og aktiviteten vises som aflyst. Var ingen tilmeldt, forsvinder den fra listen.

# Betaling og medlemskab

## Betal en regning {#betaling side:/betal/:id,/profil/:tab?}
Du betaler med MobilePay direkte fra regningen. Når betalingen er gennemført, bliver regningen automatisk markeret som betalt, og du får en kvittering.

Dine regninger, betalte og ubetalte, finder du under **Profil → Regninger**. Kan du ikke betale med MobilePay, kan kassereren registrere en kontant betaling eller bankoverførsel.

## Medlemskab og sæson {#medlemskab side:/profil/:tab?}
Sæsonen løber fra 1. maj til 30. april. Melder du dig ind fra 1. august, kan du købe halv sæson. Melder du dig ind efter sæsonafslutning, gælder medlemskabet fra næste 1. maj, men du kan som udgangspunkt deltage i aktiviteter fra indmeldelsen.

Dit medlemskab og hvornår det udløber, ser du under **Profil → Familie**.

## Familie og husstand {#familie side:/profil/:tab?}
Under **Profil → Familie** kan husstandens betaler tilføje børn eller en partner med **+ Tilføj ny person**. Personen bliver først tilføjet, når medlemskabet til vedkommende er betalt.

Har du et enkeltmedlemskab og tilføjer en person, kan husstanden skifte til familiekontingent. Så betaler du kun forskellen for resten af sæsonen.

## Tilkøb {#tilkob side:/profil/:tab?}
Under **Profil → Tilkøb** kan du købe udstyrsleje, dagsleje, opbevaringsplads og andre tilkøb. Hvert tilkøb bliver en regning, som betales som kontingent. Nogle tilkøb kræver, at du er medlem.

## Udlæg og kvitteringer {#udlaeg side:/profil/:tab?}
Har du lagt penge ud for klubben, sender du et billede eller en PDF af kvitteringen under **Profil → Kvitteringer** med en beskrivelse og beløbet. Vælg, om pengene skal udbetales til MobilePay eller en bankkonto.

Du kan følge status (afventer, udbetalt eller afvist). Kassereren overfører pengene og giver dig besked.

# Kommunikation

## Notifikationer og beskeder {#notifikationer side:/profil/:tab?,/indbakke}
Under **Profil → Beskeder** vælger du, om du vil have besked på e-mail og/eller som push-besked på telefonen, og hvilke kategorier du vil høre om (**Hvilke kategorier vil du høre om?**). Nogle kategorier, fx generalforsamling, kan ikke fravælges.

Alle beskeder fra systemet samles også i **Indbakke**.

## Opslagstavlen {#opslag side:/opslag}
Under **Opslag** kan alle medlemmer dele tekst, op til 10 billeder og 3 videoer, synes godt om og kommentere. Når du lægger billeder op, skal du bekræfte, at de afbildede er okay med det. Billeder kan kun ses af medlemmer.

Under **Inviter** kan du vælge en kategori, fx SUP, så medlemmer, der følger kategorien, får en push-besked. Du kan sende højst 3 opslag med push-besked pr. døgn.

Er et opslag upassende, kan du trykke **Anmeld**.

## Beskeder til andre medlemmer {#beskeder side:/beskeder,/beskeder/:id,/indbakke}
Under **Indbakke → Beskeder** kan voksne medlemmer skrive til hinanden. Tryk **Ny besked** og vælg en eller flere modtagere. Vælger du flere, bliver det en gruppe.

Vil du ikke have beskeder fra andre medlemmer, slår du **Andre medlemmer må skrive til mig** fra under Profil. Trænere og bestyrelse kan stadig skrive til dig.

# Min profil og mine data

## Ret mine oplysninger {#profil side:/profil/:tab?}
Under **Profil → Oplysninger** retter du navn, kontaktoplysninger og nødkontakt.

## Skift adgangskode {#adgangskode side:/profil/:tab?}
Under **Profil → Oplysninger** kan du skifte adgangskode nederst under **Skift adgangskode**. Skriv din nuværende adgangskode og den nye to gange. Den nye skal være på mindst 8 tegn. Andre telefoner og computere, du er logget ind på, bliver logget ud.

## Mine data {#mine-data side:/profil/:tab?}
Under **Profil → Mine data** ser du dine roller i klubben og kan hente alle dine data med **Hent mine data**.

# For trænere

## Trænerportalen {#traenerportal kræver:portal side:/traener}
**Trænerportal** viser de aktiviteter, du står for (kommende og afholdte), og listen over kommende aktiviteter, der mangler træner. Åbn en aktivitet og tryk **Jeg tager den** for at blive træner på den.

Under **Tilgængelighed** ser du, hvornår trænerne kan, og under **Fravær** melder du dage eller perioder, hvor du ikke kan.

## Opret en aktivitet {#opret-aktivitet kræver:activities_manage side:/aktiviteter/ny,/aktiviteter/:id/rediger,/traener}
Tryk **Ny aktivitet** i Trænerportalen (eller **Ny** under Aktiviteter). Udfyld titel, tid, sted, kategori, antal pladser og evt. svarfrist og pris.

En fast aktivitet gentages samme ugedag og tidspunkt hver uge, op til 60 gange. Hver gang får sin egen tilmelding. Faste aktiviteter oprettes uden træner, og trænerne melder sig selv på de enkelte gange.

Er klubbens udstyr allerede booket i tidsrummet, får du at vide af hvem. Du kan oprette alligevel, rette tidspunkt eller udstyr, eller oprette aktiviteten som **Afventer**. En aktivitet, der afventer, kan medlemmerne ikke se. Tryk **Opret aktiviteten nu**, når det er afklaret.

## Skift træner, meld fra eller aflys {#traenerskift kræver:portal side:/aktiviteter/:id}
På aktivitetens side kan du tage aktiviteten (**Jeg tager den**), sætte en anden træner på (**Sæt anden træner på**) eller melde fra som træner (**Meld fra**). På en fast aktivitet kan du vælge **Kun denne gang** eller **Denne og alle senere gange, hvor jeg er træner**. Står aktiviteten uden træner, får de andre trænere besked.

Du aflyser med **Aflys** og en begrundelse. De tilmeldte får besked.

## Fremmøde og deltagerliste {#fremmoede kræver:portal side:/aktiviteter/:id}
På aktivitetens side ser du deltagerlisten og kan trykke **Udskriv liste**. Efter aktiviteten markerer du hver deltager som **Mødt** eller **Udeblev** og trykker **Gem fremmøde**. Gæster og klippekort-deltagere står med mærket "Gæst".

# Udstyr

## Book udstyr {#udstyr-booking kræver:equipment side:/udstyr}
Under **Udstyr** tryk **Book udstyr**, vælg tidsrum (højst 14 dage) og de stykker udstyr, du vil låne. Udstyr, som andre har booket, kan ikke vælges. Udstyr, der er meldt **Defekt**, kan ikke bookes.

Har en aktivitet booket udstyret, kan du trykke **Send ønske til træneren**. Træneren svarer ja eller nej, og du får besked.

Dine bookinger finder du under **Udstyr → Bookinger**, hvor du også kan annullere dem.

## Når en træner beder dig flytte en booking {#udstyr-flyt kræver:equipment side:/udstyr}
Skal en aktivitet bruge udstyr, du har booket, kan træneren bede dig flytte bookingen. Du får besked med en svarfrist og kan under **Udstyr → Bookinger** trykke **Flyt booking**, annullere den eller svare **Nej, jeg har brug for det** med en begrundelse. Svarer du ikke inden fristen, kan bookingen blive annulleret.

## Udstyrets stand {#udstyr-stand kræver:equipment side:/udstyr}
Trænere og bestyrelse kan rette udstyrets stand med **Ret stand**: **OK**, **Skal serviceres** (kan stadig bruges) eller **Defekt** (kan ikke bookes). Meldes noget defekt, får dem, der har booket det, besked.

# Administration

## Medlemmer {#medlemmer kræver:members_view side:/medlemmer,/medlemmer/:id}
Under **Medlemmer** kan du søge og filtrere i kartoteket, fx alle aktive voksne eller alle med ubetalt kontingent. På medlemmets side ser du oplysninger, husstand, medlemskab og historik. Administrator kan her tildele roller og personlige rettigheder.

## Økonomi {#okonomi kræver:finance_read side:/okonomi,/okonomi/overblik,/okonomi/posteringer,/okonomi/betalinger,/okonomi/udlaeg,/okonomi/budget,/okonomi/bank}
Under **Økonomi** finder kasserer, bestyrelse og revisor overblik, posteringer, betalinger, udlæg, budget, bankafstemning og årsregnskab. Betalinger fra MobilePay bogføres automatisk. Rettelser sker med en ny postering, aldrig ved at slette. Økonomi findes både på web og i appen. I appen åbnes årsregnskabet i browseren, når det skal udskrives eller gemmes som PDF.

## Indstillinger {#indstillinger kræver:settings side:/indstillinger,/indstillinger/rettigheder,/indstillinger/aendringslog}
Under **Indstillinger** opretter bestyrelse og administrator kategorier, medlemskaber, tilkøb og tilbud til ikke-medlemmer og retter priser med virkning fra en valgt dato. Under **Rettigheder** vælger administrator, hvilke roller der må hvad. **Ændringslog** viser, hvem der har gjort hvad og hvornår.

# Hjælp

## Farvetemaer {#design kræver:design_manage side:/indstillinger/design}
Under **Indstillinger → Design** laver du klubbens egne farvetemaer: farver, skrift og runde hjørner. Logoet er altid klubbens. Sidernes opbygning ændres aldrig, så alt virker på både mobil og pc.

- Værktøjslinjen øverst har **Tema**, **Gem**, **Farver** og **Skrift og hjørner**. Et tryk åbner en værktøjsboks oven på forhåndsvisningen. Den kan flyttes ved at trække i overskriften og lukkes med ×.
- De indbyggede temaer (fx SurfHorsens) kan ikke rettes. Tryk **Åbn kopi** og gem kopien under et nyt navn.
- Tryk **Rediger** ved et af klubbens egne temaer for at rette det. **Gem** gemmer ændringerne, og **Gem som…** gemmer dem som et nyt tema, så det gamle forbliver uændret.
- Vælg **Lys udgave** eller **Mørk udgave**, og tryk på en farve for at ændre den. Den mørke udgave bruges, når telefonen eller computeren står på mørk tilstand.
- **Under udvikling**: kun dig og andre, der må rette design, kan vælge temaet under Farvetema og prøve det af. **Tilgængeligt for medlemmer**: alle kan vælge det.
- **Kan man læse det?** advarer, hvis tekst og baggrund ligger for tæt på hinanden. Ret farven, til advarslen forsvinder.

Sletter du et tema, får medlemmer, der havde valgt det, Automatisk (SurfHorsens) i stedet.

## Hjemmesiden {#hjemmeside kræver:site_edit side:/indstillinger/hjemmeside}
Under **Indstillinger → Hjemmeside** retter du klubbens offentlige sider: Forside, Priser og medlemskab, Så meget AlÅha og Kontakt. Siderne er bygget af faste blokke, så de altid virker på både mobil og pc.

- Vælg siden øverst til venstre. Forhåndsvisningen viser siden, som den kommer til at se ud. Skift mellem **Mobil** og **Computer**.
- **Klik på en blok** i forhåndsvisningen for at rette den. Værktøjsboksen viser kun den blok: billede, tekst og knapper. Boksen kan flyttes ved at trække i overskriften.
- **Billeder:** tryk **Skift billede** og vælg et af klubbens billeder eller upload et nyt. Det gøres automatisk mindre og renses for oplysninger som GPS-position. Skriv altid en kort beskrivelse af billedet – den læses op for svagtseende.
- **Fjern et billede** (×), så breder teksten sig over hele bredden.
- **Knapper** fører til en side, indmeldelsen, et tilbud til ikke-medlemmer (fx en prøvetime) eller en aktivitet i kalenderen. Du vælger fra en liste. Er tilbuddet eller aktiviteten væk eller overstået, forsvinder knappen af sig selv.
- **Tekst:** brug knapperne over teksten til overskrifter, fed, kursiv, punktlister og links. **Pris** indsætter en pris fra Pakker, som altid følger den aktuelle pris.
- **Skjul blokken** fjerner den fra hjemmesiden uden at slette den. **Tilføj blok** sætter en ny blok ind under den valgte.
- **↶ Fortryd** fortryder den seneste ændring. **Udgiv** lægger ændringerne på hjemmesiden. Under **Historik** kan du hente en tidligere udgave.

Kun administratoren kan ændre layoutet (en eller to kolonner og bredder), slette blokke og rette siden Kalender.

## Finder du ikke svaret? {#spoerg}
Søg i feltet øverst i hjælpen. Kommer du ikke videre, kan du kontakte bestyrelsen.
