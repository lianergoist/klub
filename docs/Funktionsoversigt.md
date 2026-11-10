# Funktionsoversigt – klubsystem til windsurfingklub

## Formål og sådan læses dokumentet

Systemet skal samle medlemmer, aktiviteter og økonomi ét sted, så klubbens frivillige bruger tid på at sejle frem for at jonglere med regneark, mails og betalinger. Dokumentet beskriver *hvad* systemet skal kunne, ikke *hvordan* det bygges; databasefelter og teknik kommer senere.

Hver funktion er markeret, så bestyrelsen kan prioritere:

- **Kerne** – skal med fra start, ellers giver systemet ikke mening.

- **Senere** – nyttigt, men kan vente til anden fase.

- **Afklares** – bestyrelsen skal tage stilling, før vi kan beskrive den.

  Alle Afklares-punkter er samlet i oversigten [Klubsystem – beslutninger til bestyrelsen](https://claude.ai/artifact/86rV28YF85roQiNpJvRL8s), hvor bestyrelsen kan skrive sine beslutninger. Når et punkt er besluttet, skrives beslutningen ind her.

Kommentér gerne direkte i teksten, ret og tilføj. Afsnittet om økonomi er skrevet med forklaringer, da det er der, der er flest valg at tage stilling til.

## Roller og rettigheder

Et medlem kan have flere roller samtidigt (fx træner og bestyrelsesmedlem). Roller styrer, hvad man kan se og gøre. Oversigten er et første bud, som bestyrelsen skal justere.

| Rolle | Kan | Kan ikke |
| - | - | - |
| Medlem | Se og redigere egen profil, tilmelde sig aktiviteter, betale, se egen historik | Se andres oplysninger ud over navn på deltagerlister |
| Træner | Oprette og redigere aktiviteter, skifte træner, se deltagerlister, angive utilgængelighed | Se økonomi eller medlemmers betalingsoplysninger |
| Bestyrelsesmedlem | Det træneren kan, plus se medlemskartotek og tilgængelighed for alle trænere | Rette i regnskabet |
| Kasserer | Indtaste indtægter og udgifter, styre kontingent og betalinger, trække rapporter | Ændre roller (kun admin) |
| Revisor | Kun læseadgang til regnskab, bilag og ændringslog | Rette noget som helst |
| Administrator | Tildele roller, rette medlemsdata, rette systemindstillinger | – |

- **Kerne: rollestyring som ovenfor, og at roller kan tildeles og fjernes af administrator med dato (historik).**


## Medlemskartotek

Kartoteket er systemets rygrad: alt (aktiviteter, kontingent, betalinger) hænger på et medlem.

- **Kerne:** profil med navn, e-mail, telefon, adresse og fødselsdato (alder bruges til børne-/voksenkontingent og evt. tilskud).

- **Kerne:** medlemmer kan selv oprette sig, rette egne oplysninger og se egen historik. Indmeldelse kræver ikke godkendelse: man sendes direkte videre til betaling af kontingent, og når betalingen er gennemført, er man aktivt medlem. Indtil da har medlemmet status "afventer betaling". Bestyrelse/kasserer kan aktivere manuelt (fx ved kontant betaling eller æresmedlemskab).

- **Kerne:** familier/husstande: ét betalende medlem (forælder) og flere tilknyttede medlemmer (børn, partner). Forælder kan tilmelde børn til aktiviteter.

- **Kerne:** skift til familiekontingent: tilføjer et medlem med individuelt kontingent et barn eller en partner, skiftes husstanden til familiekontingent, og betaleren sendes direkte til betaling af forskellen for resten af året. Personen tilføjes først, når betalingen er gennemført; gennemføres betalingen ikke, sker der intet. Har husstanden allerede familiekontingent, tilføjes personen uden betaling.

- **Kerne:** medlemsstatus (afventer betaling, aktiv, passiv, udmeldt) med datoer for indmeldelse og udmeldelse. Udmeldte slettes ikke med det samme, da økonomien stadig skal kunne spores.

- **Afklares:** hvad sker der med indmeldelser, der aldrig bliver betalt? Fx en påmindelse efter 3 dage og sletning af oplysningerne efter 30 dage.

- **Afklares:** må børn og unge under 18 melde sig ind selv, eller skal det altid ske via en forælder, der også betaler og giver samtykke?

- **Kerne:** roller (medlem, træner, bestyrelse, kasserer, revisor) med start- og slutdato, så man kan se, hvem der sad hvornår.

- **Kerne:** søgning og filtrering, fx alle aktive voksne, alle trænere, alle med ubetalt kontingent.

- **Kerne:** kontaktperson og evt. nødkontakt, især for børn og unge.

- **Senere:** niveau/certifikater pr. medlem (fx duelighedsbevis, kurser, førstehjælp) og hvem der må låne hvilket udstyr.

- **Senere:** egne noter, fx allergier eller svømmefærdigheder, kun synlige for trænere. Kræver særlig opmærksomhed omkring persondata (se afsnittet om persondata).

- **Senere:** import af eksisterende medlemsliste fra regneark ved opstart.


## Træninger og aktiviteter

Trænere og bestyrelse opretter aktiviteter. Medlemmer ser dem og tilmelder sig.

**Oprettelse**

- **Kerne:** titel, beskrivelse, dato og tid, sted, kategori, max antal deltagere og evt. min. antal. 

- **Kerne:** kategorier (windsurf, kite, SUP, wingfoil m.fl.). Bestyrelsen kan selv tilføje og omdøbe kategorier. En aktivitet kan have flere kategorier.

- **Kerne:** svarfrist, som sættes ved oprettelsen. Efter fristen kan man ikke tilmelde sig uden trænerens accept.

- **Kerne:** den, der opretter, kan tilføje sig selv og andre trænere som træner/tovholder.

- **Kerne:** valgfrit pris for deltagelse. Hvis der er en pris, skal betalingen være en del af tilmeldingen (se afsnittet om betaling).

- **Kerne:** oprettelsen sender notifikation til alle medlemmer (se afsnittet om notifikationer).

- **Senere:** gentagne aktiviteter (fx hver onsdag kl. 18 hele sæsonen) og kopi af tidligere aktivitet.

  - Dvs også oprette aktiviteter uden at sætte træner/tovholder på. /Thomas

- **Senere:** forudsætninger (kræver fx duelighedsbevis eller eget udstyr), så systemet kan advare ved tilmelding.

- **Senere:** aflysning med begrundelse, som automatisk giver besked og refunderer betaling.

- **Senere:** vejrnote eller "afgøres dagen før", fordi mange aktiviteter afhænger af vind.

**Trænerskift og historik**

- **Kerne:** alle trænere kan ændre, hvem der er træner på en aktivitet.

- **Kerne:** hver ændring logges med hvem, hvornår og hvad (fx "Lars blev erstattet af Mette den 3. marts"). Historikken kan ses af trænere og bestyrelse.

- **Senere:** ved trænerskift får de berørte trænere automatisk besked, og tilmeldte får besked, hvis træner/tovholderen skifter.

## Tilmelding og deltagelse

- **Kerne:** medlemmer ser tre lister: kommende aktiviteter, aktiviteter jeg er tilmeldt, og aktiviteter jeg har deltaget i.

- **Kerne:** filter på kategori, så man fx kun ser SUP og wingfoil. Valget kan gemmes som standard.

- **Kerne:** tilmelding og afmelding med et tryk. Afmeldingen kan være spærret efter en frist, fx 24 timer før start.

- **Kerne:** historik pr. tilmelding: hvornår man meldte sig til, evt. meldte fra igen og evt. tilmeldte sig igen. Synlig for medlemmet selv, trænere og bestyrelse.

- **Kerne:** træneren kan efter aktiviteten markere, hvem der faktisk mødte op. Det er grundlaget for "aktiviteter jeg har deltaget i".

- **Kerne:** tilmelding for børn via forældre og for flere familiemedlemmer på én gang.

- **Senere:** venteliste, hvor næste på listen automatisk får pladsen og besked, hvis nogen melder fra.

- **Senere:** mulighed for at tage gæst med?

- **Senere:** mærkning af udeblivelser uden afbud (nyttigt, hvis aktiviteten har begrænsede pladser eller koster klubben penge).

- **Afklares:** hvad sker der med betalingen, hvis man melder fra? Refunderes ved frameldingen før en bestemt frist, ellers ikke? (se afsnittet om betaling)

## Trænerportal og tilgængelighed

- **Kerne:** alle træneren ser en oversigt over aktiviteter, hun/han står for, både kommende og afholdte, med deltagerliste og fremmøde.

- **Kerne:** træneren kan se historikken for, hvem der har været træner på hver aktivitet, hvis der er sket ændringer siden oprettelsen.

- **Kerne:** træneren kan angive dage eller perioder, hvor hun/han ikke er til rådighed (ferie, arbejde, skade).

- **Kerne:** trænere og bestyrelse kan se en samlet kalender over, hvem der er til rådighed hvornår, når nye aktiviteter skal planlægges.

- **Kerne:** hvis en træner har markeret sig utilgængelig, advarer systemet ved forsøg på at sætte vedkommende på en aktivitet i perioden.

  - Eller, navnet vises ikke i listen over trænere der kan sættes på en aktivitet. /Thomas

- **Senere:** trænere kan skrive en kort efterrapport pr. aktivitet (vejr, antal, hændelser, udstyr der trænger til reparation).

- **Senere:** trænere kan angive, hvilke kategorier de kan undervise i (fx windsurf og SUP, men ikke kite).


## Notifikationer og kommunikation

- **Kerne:** når en aktivitet oprettes, får alle medlemmer besked med titel, tid, svarfrist og link til tilmelding.

- **Kerne:** medlemmer vælger selv kanal (e-mail, evt. push-besked i telefonen) og kan fravælge kategorier, de ikke er interesseret i. Så får en wingfoil-gruppe ikke beskeder om SUP.

- **Kerne:** påmindelse før svarfristen udløber til dem, der endnu ikke har svaret.

  - Med *mulighed* for at sætte påmindelse ved oprettelse af aktivitet. /Thomas

- **Kerne:** bekræftelse ved tilmelding, afmelding og betaling.

- **Kerne:** besked til tilmeldte ved aflysning eller ændring af tid/sted.

- **Senere:** besked til kassereren, når en betaling mangler eller fejler, og automatiske rykkere (se betaling).

- **Senere:** månedlige beskeder fra bestyrelsen til alle eller udvalgte grupper (fx alle trænere, alle forældre).

- **Afklares:** skal alle medlemmer have beskeder som standard, eller skal man aktivt vælge det til? Det har betydning for databeskyttelse og for, hvor mange der kommer til at blive irriterede.

## Opslagstavle (medlemsindhold)

Et socialt feed, hvor medlemmerne deler oplevelser, billeder og spørgsmål – i stedet for (eller som supplement til) en lukket Facebook-gruppe.

- **Kerne:** alle aktive medlemmer kan lægge opslag op med tekst og op til 4 billeder, synes godt om (hjerte) og kommentere. Man kan slette sine egne opslag og kommentarer.

- **Kerne:** ved upload af billeder skal man bekræfte, at de afbildede er okay med at blive vist. Billeder skaleres ned, og metadata (fx GPS-position) fjernes automatisk. Billeder kan kun ses af indloggede medlemmer.

- **Kerne:** alle kan anmelde et opslag med en begrundelse. Trænere og bestyrelse får besked og kan skjule opslaget (forfatteren får besked med begrundelsen), vise det igen eller afvise anmeldelsen. Moderatorer kan også skjule enkelte kommentarer. Alt logges.

- **Senere:** opslag knyttet til en aktivitet (fx billeder fra kurset), fastgjorte opslag fra bestyrelsen, push-besked ved nye kommentarer.

- **Afklares:** regler for opslagstavlen (hvad må deles, reklame/salg, billeder af børn) – bør formuleres af bestyrelsen og vises for medlemmerne.

- **Afklares:** skal alle trænere kunne moderere, eller kun udvalgte personer? Hvor længe gemmes opslag og billeder, og skal de slettes, når et medlem melder sig ud?

## Beskeder mellem medlemmer

Private beskeder mellem medlemmer – to eller flere i samme samtale, fx forælder og træner, eller en gruppe der vil aftale at tage på vandet.

- **Kerne (i proof of concept):** samtaler mellem voksne medlemmer (18+) med eget login. Under "Ny besked" vælger man én eller flere fra en liste over alle medlemmer, der modtager beskeder (kun navne vises, ingen kontaktoplysninger), eller trykker "Skriv" på et opslag. Vælger man flere, oprettes en gruppe, som kan få et navn.

- **Kerne (i proof of concept):** i grupper kan man se hvem der har skrevet hvad og hvem der har læst, tilføje flere deltagere, omdøbe gruppen og forlade den. Ulæste beskeder vises i Indbakke, og der sendes højst én mail pr. samtale, indtil beskederne er læst.

- **Kerne (i proof of concept) – regler:**
  - Har man slået beskeder fra, kan andre medlemmer ikke skrive til en, og man kan selv kun skrive med trænere og bestyrelse (inkl. svare dem og skrive i grupper, de har startet).
  - Har man blokeret et medlem, kan man hverken skrive til vedkommende eller modtage beskeder fra vedkommende, før blokeringen fjernes.
  - Trænere og bestyrelsesmedlemmer kan ikke blokeres – klubben skal altid kunne nå medlemmerne. Oplever man problemer med en træner eller et bestyrelsesmedlem, kan man anmelde beskeden.
  - Trænere og bestyrelse kan skrive til alle voksne medlemmer – også dem, der har slået beskeder fra.
  - I grupper går beskeder ud til alle deltagere, uanset personlige blokeringer. Blokering gælder kun samtaler mellem to.
  - Man kan anmelde en besked. Bestyrelsen får den anmeldte besked; ellers kan kun deltagerne i samtalen læse beskederne.

- **Senere:** færdige grupper ud fra en aktivitet (alle tilmeldte) eller en rolle (alle trænere), billeder i beskeder, push-besked på telefonen.

- **Afklares:** skal beskeder med i det endelige system (fase 1 eller senere), eller er opslagstavlen og notifikationer nok?

- **Afklares:** må børn og unge under 18 bruge beskeder? I proof of concept kan de hverken skrive eller modtage. Alternativ: trænere skriver til forældrene, ikke til barnet direkte.

- **Afklares:** er det i orden, at alle medlemmer kan se en liste med navnene på alle andre voksne medlemmer (der ikke har fravalgt beskeder)? Skal medlemmer i stedet aktivt vælge at være synlige (fravalg vs. tilvalg)?

- **Afklares:** hvor længe gemmes beskeder, og skal de slettes, når et medlem melder sig ud? Hvem i bestyrelsen modtager anmeldte beskeder?

## Kontingent og medlemstyper

Kontingent er klubbens faste indtægt. Systemet skal kunne beregne, opkræve og følge op på det uden manuel regnearksstyring.

**Træningssæsonen løber 1. maj – 30. april. Regnskabsåret er kalenderåret (1. januar – 31. december).** Kontingent og sæsonprodukter følger sæsonen; regnskab og budget følger kalenderåret.

| Pris (fra 1. maj 2026) | Beløb |
| - | - |
| Støttemedlem, fuld sæson | 400 kr. |
| Enkelt medlem, fuld sæson | 1.000 kr. |
| Enkelt medlem, halv sæson (indmeldelse fra 1. august) | 400 kr. |
| Familiekontingent, 3 personer i samme husstand | 2.500 kr. |
| Familiekontingent, 4 eller flere i samme husstand | 3.000 kr. |

- **Kerne:** medlemstyperne er *enkelt medlem*, *familie* og *støttemedlem*. Kontingentet beregnes pr. husstand ud fra antallet af aktive medlemmer (støttemedlemmer tæller ikke med): 1–2 personer betaler enkeltkontingent hver, 3 personer familiekontingent, 4 eller flere familiekontingent for store familier.

- **Kerne (i proof of concept):** alle priser (kontingent, udstyrsleje, opbevaring, prøvetræning, SUP-kursus, depositum) ligger samlet under Indstillinger, hvor bestyrelse og kasserer selv kan lægge en ny pris ind med virkning fra en valgt dato (i dag eller senere – aldrig bagud). Den gamle pris bevares som historik, regninger der allerede er sendt ændres ikke, og en kommende pris kan fortrydes, indtil den træder i kraft. Alle ændringer skrives i ændringsloggen.

- **Kerne:** kassereren opretter kontingentopkrævning for en sæson med ét tryk: én regning pr. husstand og én pr. støttemedlem, sendt til husstandens betaler.

- **Kerne:** betalingsstatus pr. medlem og sæson: ikke opkrævet, opkrævet, betalt, forfalden.

- **Kerne:** halv sæson: melder et enkelt medlem sig ind fra 1. august, betales halvsæsonprisen.

- **Kerne:** tilføjes et barn eller en partner midt i sæsonen, betaler husstanden forskellen mellem den nye husstandspris og det, der allerede er betalt for sæsonen (fx enkelt medlem på halv sæson 400 kr. → 2 personer 800 kr. → 3 personer 2.500 kr.).

- **Kerne:** aktiviteter (og evt. dele af systemet) kan kræve gyldigt medlemskab, dvs. betalt kontingent for den aktuelle sæson.

- **Afklares:** en husstand med 2 personer betaler i proof of concept 2 × enkeltkontingent (2.000 kr.), da familiekontingent først gælder fra 3 personer. Er det rigtigt?

- **Afklares:** børn og trænere har ingen særpris og betaler som enkelt medlem. Er det rigtigt, eller skal der være en børnepris eller gratis/nedsat kontingent for trænere?

- **Afklares:** halv sæson gælder kun enkelt medlem. Skal familier og støttemedlemmer, der melder sig ind fra 1. august, også have en halvsæsonpris? Og skal en familie, der vokser fra 2 til 3 personer sent i sæsonen, betale fuld familiepris minus det betalte (som nu)?

- **Afklares:** kontingent for en sæson dækker to regnskabsår (maj–december og januar–april). I proof of concept bogføres det, når det betales. Skal det fordeles (periodiseres) på de to regnskabsår? Bør afklares med revisor.

- **Senere:** rabatter og særtilbud (fx søskendekontingent).

- **Afklares:** hvad sker der ved manglende betaling? Fx rykker efter 14 dage, spærring for tilmelding efter 30 dage, udmeldelse efter en så lang tid? Det skal bestyrelsen beslutte, systemet kan så udføre det.

## Tilkøb og øvrige betalinger

Udover kontingent betaler medlemmer for ting, der bruger klubbens ressourcer. Systemet har en **produktkatalog**, som kassereren/bestyrelsen vedligeholder, og medlemmet vælger fra den.

| Eksempel på produkt | Betalingsform | Bemærkning |
| - | - | - |
| Udstyrsleje i træningssæsonen (bræt, sejl, våddragt) | Pr. sæson | Enkelt medlem 1.500 kr., familie 4.000 kr. (op til 3 personer) + 1.000 kr. pr. ekstra person |
| Dagsleje af udstyr | Pr. gang | Foreløbig pris – ikke fastsat |
| Prøvetræning | Engangsbetaling | 400 kr. |
| Opbevaring af udstyr (plads, rack, container) | Pr. sæson eller år | Kræver en plads, der kan være optaget |
| Deltagelse i aktivitet/kursus | Pr. aktivitet | Se tilmelding |
| Nøgle/adgangsbrik, depositum | Engangsbetaling | 100 kr. Depositum skal kunne refunderes |
| Klubtøj, merchandise | Pr. stk. | Senere, kun hvis klubben sælger |



- **Kerne:** produktkatalog med navn, pris, betalingsform (engang, pr. periode) og hvem det gælder for (fx pris for medlem vs. gæst).

- **Kerne:** medlemmet kan tilvælge produkter i profilen og se, hvad det koster.

- **Kerne:** hvert tilkøb bliver en regning med betalingsstatus, på samme måde som kontingent.

- **Kerne:** udstyrsleje bestilles for én eller flere i husstanden og prissættes samlet: 1–2 personer betaler enkeltpris hver, 3 personer familiepris, derover + pr. ekstra person. Lejen gælder sæsonen ud (30. april).

- **Afklares:** priserne for opbevaring (rackplads 600 kr., skab 400 kr.) og dagsleje (100 kr.) er foreløbige. Bestyrelsen kan selv rette dem under Indstillinger.

- **Kerne (i proof of concept):** ikke-medlemmer kan på siden "Prøv SurfHorsens" købe en **prøvetræning (400 kr.)** eller et **SUP-kursus (500 kr.)**. De vælger en dato blandt de aktiviteter, trænerne har åbnet for gæster, udfylder navn, kontaktoplysninger og fødselsdato (under 18: forælders navn og telefon) og betaler med MobilePay. De står derefter på deltagerlisten mærket "Gæst", men har ikke adgang til medlemsdelen.

- **Kerne (i proof of concept):** melder gæsten sig ind inden for 12 måneder (med samme e-mail), trækkes det betalte fra kontingentet. Dækker beløbet hele kontingentet, er man medlem med det samme uden yderligere betaling.

- **Afklares:** hvis gæstekøbet er større end kontingentet (fx SUP-kursus 500 kr. og halv sæson 400 kr.), bortfalder resten i proof of concept. Skal det overskydende kunne bruges til fx udstyrsleje?

- **Afklares:** skal medlemmer også betale for SUP-kurset (i proof of concept: 500 kr. for alle), og skal en prøvetræning/et kursus kun modregnes inden for 12 måneder?

- **Afklares:** hvor længe gemmes oplysninger om gæster, der ikke melder sig ind?

- **Kerne:** opbevaringspladser og lejeudstyr registreres, så samme plads eller samme bræt ikke bliver udlejet to gange.

- **Senere:** kalender for udstyrslejen med booking pr. dag og tid, samt retur-registrering.

  - Forslag: oversigt over udstyr og tilstand. /Thomas

- **Senere:** ventelister på opbevaringspladser.

- **Afklares:** er det kun medlemmer, der kan leje, eller også gæster? Skal der være depositum?

## Online betaling

Betaling sker via en ekstern betalingsudbyder. Systemet gemmer ikke kortnumre; det modtager kun besked fra udbyderen om, at en betaling er gennemført, fejlet eller refunderet. Klubben skal have en aftale med udbyderen, og udbyderen tager typisk et gebyr pr. betaling. Valg af udbyder og pris er en bestyrelsesbeslutning (se sidste afsnit).

- **Kerne:** betaling med MobilePay og evt. betalingskort fra regningen, direkte i systemet.

- **Kerne:** når en betaling lykkes, bliver regningen automatisk markeret som betalt, og medlemmet får en kvittering. Kassereren skal ikke afstemme dem én for én.

- **Kerne:** tilmelding til betalingsaktivitet færdiggøres først, når betalingen er gennemført (plads reserveres kort mens man betaler).

- **Kerne:** oversigt for kassereren over åbne, betalte og forfaldne betalinger, pr. medlem og pr. produkt.

- **Kerne (i proof of concept, med simuleret afregningsrapport):** automatisk afstemning af MobilePay-udbetalinger: klubsystemet henter Vipps MobilePays afregningsrapport, kobler hver samlet udbetaling i banken til de enkelte betalinger og bogfører gebyrerne. (Hver betaling kobles allerede til sin regning via betalingens id – det er kun den samlede udbetaling til banken, der skal afstemmes.)

- **Kerne:** kassereren kan registrere en betaling manuelt (kontant eller bankoverførsel), så de, der ikke bruger MobilePay, også kan betale.

- **Kerne:** refusion (helt eller delvist), fx ved aflyst aktivitet. Refusionen kræver, at kassereren eller en anden med tilladelse godkender, og logges.

- **Senere:** automatiske rykkere for forfaldne betalinger.

- **Senere:** betalingsaftaler (abonnement), så kontingentet trækkes automatisk hvert år eller hver måned.

- **Senere:** betalingsplaner for familier med flere medlemmer.

- **Afklares:** hvilken udbyder? Hvem i klubben opretter aftalen og kan se transaktionerne? Skal klubben dække gebyret eller lægge det oven i prisen? Hvor ofte skal MobilePay udbetale til banken (dagligt, ugentligt)? Proof of concept regner med dagligt og 1 % i gebyr.

## Økonomi og regnskab

En forenings regnskab handler grundlæggende om tre ting: at **alle indtægter og udgifter er bogført med bilag**, at **regnskabet stemmer med banken**, og at **bestyrelsen og revisor kan se, hvad der er sket**.

**Kassererens funktioner**

- **Kerne:** indtaste indtægter og udgifter med dato, beløb, tekst, kategori (se kontoplan nedenfor) og bilag (foto eller PDF af kvittering/faktura).

- **Kerne:** indtægter fra kontingent, aktiviteter og tilkøb bogføres automatisk, når en betaling er gennemført. Kassereren skal kun indtaste det, der ikke kommer af sig selv (sponsorater, tilskud, indkøb).

- **Kerne:** en enkel **kontoplan**: faste kategorier som kontingent, aktivitetsindtægter, udstyrsleje, tilskud, sponsorer, indkøb af udstyr, vedligehold, forsikring, kurser, trænerudgifter, administration og gebyrer. Bestyrelsen kan tilføje flere.

- **Kerne:** tilknytte en postering til en aktivitet eller et projekt, så man kan se, hvad en enkelt aktivitet kostede og indbragte.

- **Kerne:** registrere **udlæg**, hvis en træner eller et bestyrelsesmedlem har betalt noget for klubben. Det kan godkendes og udbetales.

- **Kerne:** rettelser sker ved en ny postering, der udligner den gamle, og ikke ved at slette. Alt har dato, og hvem der indtastede det.

- **Kerne:** bankafstemning: kassereren kan uploade kontoudtog (CSV fra banken) og matche det mod posteringerne for at se, om noget mangler eller afviger.

**Overblik og kontrol**

- **Kerne:** aktuel saldo og resultat (indtægter minus udgifter) for regnskabsåret, fordelt på kategorier.

- **Kerne:** budget pr. kategori og sammenligning af budget og faktisk resultat, så bestyrelsen kan se, om klubben holder budgettet.

- **Kerne:** årsregnskab til generalforsamlingen: indtægter og udgifter, balance (hvad klubben ejer og skylder), noter.

- **Kerne:** revisor får læseadgang til alle posteringer, bilag og ændringslog, uden at kunne ændre.

- **Senere:** to-trins-godkendelse af større udgifter (fx over et beløb bestyrelsen fastslår kræver to underskrifter).

- **Senere:** gentagne udgifter (forsikring, hjemmeside) oprettes én gang.

- **Senere:** Registrering af kommunale tilskud og tilskud fra forbund samt de medlemsdata, de kræver.


- **Afklares:** skal bogføringen ske i klubsystemet, eller i et eksternt regnskabsprogram (fx Dinero eller e-conomic), som klubsystemet sender betalingerne til? Klubsystemet fører et enkelt indtægts- og udgiftsregnskab og kan ikke moms, løn eller dobbelt bogholderi. Se [Oekonomi.md](Oekonomi.md) for hvad økonomidelen kan og ikke kan, og hvornår et regnskabsprogram er tilrådeligt eller nødvendigt.

- **Afklares:** har klubben (eller forventer den) momspligtigt salg eller lønnede trænere/honorarer? Så er et regnskabsprogram sandsynligvis nødvendigt.

**Emner bestyrelsen bør afklare med en revisor eller fagperson**

- Hvilke dokumentations- og opbevaringskrav der gælder for bilag.


## Rapporter og eksport

| Rapport | Til hvem | Prioritet |
| - | - | - |
| Medlemsliste med type, status og betalingsstatus | Bestyrelse, kasserer | Kerne |
| Deltagerliste pr. aktivitet (til træner på dagen) | Trænere | Kerne |
| Aktivitetsoversigt: antal aktiviteter, deltagere, fremmøde pr. kategori | Bestyrelse | Kerne |
| Regnskab: resultat pr. kategori, budget mod faktisk, årsregnskab | Kasserer, bestyrelse, revisor | Kerne |
| Ubetalt kontingent og forfaldne betalinger | Kasserer | Kerne |
| Medlemsudvikling over tid (tilmeldte, udmeldte, aktive) | Bestyrelse | Senere |
| Trænerbelastning: antal aktiviteter pr. træner | Bestyrelse | Senere |
| Medlemsliste til tilskud/forbund | Kasserer, formand | Afklares |

- **Kerne:** alle lister kan eksporteres til regneark (CSV/Excel), og regnskabet kan udskrives eller gemmes som PDF til generalforsamlingen.

- **Senere:** enkelt overblik ("dashboard") for bestyrelsen med de vigtigste tal.


## Persondata, sikkerhed og drift

Systemet gemmer navne, adresser, fødselsdatoer og børns oplysninger, så foreningen er omfattet af databeskyttelsesreglerne (GDPR). Det bør bestyrelsen have med fra start, ikke bygge på bagefter.

- **Kerne:** login med e-mail og adgangskode, med mulighed for at nulstille selv. For kasserer og bestyrelse anbefales to-trins-login.

- **Kerne:** rettigheder efter rolle (se rolleafsnittet). Medlemmer ser kun egne oplysninger og andres navne på deltagerlister.

- **Kerne:** samtykke ved oprettelse: hvad dataene bruges til, og at forælder giver samtykke for børn.

- **Kerne:** medlemmer kan se, rette og få hentet deres egne data.

- **Kerne:** regler for sletning: udmeldte medlemmers oplysninger fjernes eller anonymiseres efter en fastsat frist, men regnskabsbilag gemmes så længe, som reglerne kræver.

- **Kerne:** medlemmer kan selv melde sig ud og få slettet deres data – både i appen og på weben. Apple kræver, at en konto oprettet i appen kan slettes i appen. Da konto og medlemskab er det samme, betyder det udmeldelse plus sletning/anonymisering af persondata; regninger og betalinger bevares i den lovpligtige periode.

- **Afklares:** én samlet knap ("Meld dig ud og slet mine data") eller to ("Meld dig ud" med data gemt en periode, og "Slet mine data")?

- **Afklares:** hvad sker der med børn og partner, når husstandens betaler melder sig ud? Hvad med refusion af kontingent midt i sæsonen og ubetalte regninger? Skal opslag og beskeder slettes eller stå som "Tidligere medlem"?

- **Kerne:** ændringslog over, hvem der har rettet i medlemsdata, roller og regnskab.

- **Kerne:** automatisk sikkerhedskopi og en plan for, hvem der kan genskabe systemet.

- **Kerne:** backup opdelt pr. område med hver sin opbevaringstid: regnskab og bilag (længst), medlemsdata (efter slettereglerne) og opslag, billeder og beskeder (kortest). Data ligger fortsat i én database – opdelingen sker i backuppen.

- **Kerne:** krypteret kopi af backuppen uden for serveren, og en gendannelsestest mindst én gang om året.

- **Kerne:** begrænsede databaserettigheder. Appens databasebruger kan oprette posteringer og logposter, men ikke rette eller slette dem, så regnskab og ændringslog ikke kan manipuleres, selv hvis appen kompromitteres. Ændringer af databasens struktur køres med en separat bruger, som appen ikke kender. (Opdeling i flere databaser frarådes: data hænger sammen – en betaling skal på én gang markere regningen betalt, bogføre og aktivere medlemmet – og appen skulle alligevel kende alle adgangskoder.)

- **Kerne:** billeder på opslagstavlen kun med samtykke fra de afbildede (se Opslagstavle).

- **Senere:** video fra aktiviteter, kun med samtykke fra de afbildede.

- **Afklares:** hvor længe skal backup gemmes for hvert område? Forslag: regnskab og bilag 5 år efter regnskabsårets udløb (bogføringslovens frist – bekræft med revisor), medlemsdata efter slettereglerne, opslag/billeder/beskeder fx 3 måneder.

- **Kerne:** driften varetages af et eksternt firma efter kontrakt med klubben. Klubben er dataansvarlig og firmaet databehandler, så der skal være en databehandleraftale (GDPR art. 28) om bl.a. sikkerhed, backup, underleverandører (VPS-udbyder, GitHub) og data ved ophør.

- **Afklares:** hvem er "dataansvarlig" i foreningen, og hvor længe skal medlemsdata gemmes? Hvor skal systemet køre (egen server eller hosting), og hvem har ansvar for drift og opdateringer?

- **Afklares:** omfatter udbyderens daglige serverbackup hele disken (inkl. databasedumps i `/var/backups/klub`), og hvor længe gemmes backupperne? Hvem i klubben har adgang til serveren og til koden på GitHub, hvis den nuværende udvikler ikke er til rådighed?

- **Afklares:** bestyrelsen bør ikke gemme CPR-numre i systemet; kommuner og forbund kan dog kræve alder eller fødselsdato i forbindelse med tilskud.


## Forslag til faseopdeling

Et system med alt på én gang er stort at bygge og svagt at teste. Forslaget er at tage det i tre træk, så klubben kan bruge det efter første fase:

1. **Medlemmer og aktiviteter:** kartotek, roller, oprettelse af aktiviteter med kategorier og trænere, tilmelding, historik, notifikationer, trænerportal, tilgængelighed og opslagstavle. Da indmeldelse nu kræver betaling, skal online betaling af kontingent med i fase 1.

2. **Kontingent og betaling:** medlemstyper, kontingentopkrævning, online betaling, produktkatalog (leje og opbevaring), betaling for aktiviteter.

3. **Regnskab og rapporter:** kassererens indtastning, kontoplan, bilag, bankafstemning, budget, årsregnskab, revisoradgang, eksport.

Regnskab kommer sidst, fordi det bygger på de betalinger, der er kommet i fase 2.

Punkterne markeret **Senere** ovenfor lander efter fase 3 eller spædes ind undervejs efter behov.

