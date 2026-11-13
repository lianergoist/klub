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

- **Kerne:** administrator tildeler og afslutter roller under Medlemmer → medlemmets side → Roller → "Tildel rolle" (knappen vises kun for administratorer).
- **Kerne:** tabellen ovenfor er kun standarden. Under **Indstillinger → Rettigheder** er alle handlinger, der kræver en rolle, samlet i en liste (aktiviteter, medlemmer, kommunikation, økonomi), og administrator vælger for hver handling, hvilke roller der giver adgang. Ændringer gælder straks og skrives i ændringsloggen. En handling, der er ændret fra standarden, er markeret og kan sættes tilbage.
- **Kerne:** **personlige rettigheder:** administrator kan give et enkelt medlem én rettighed uden hele rollen (fx må Y godkende nye medlemmer uden at sidde i bestyrelsen), med fra-dato, evt. til-dato og en bemærkning. Det sker på medlemmets side under "Personlige rettigheder", hvor man også kan se, hvad medlemmet må i dag.
- **Kerne:** "Tildele roller og rettigheder" er låst til administrator, så ingen kan låse sig selv ude. Nye funktioner (fx udstyr) får deres egne rettigheder i listen.
- **Afklares:** hvilke roller skal have hvilke rettigheder? Proof of concept starter med standarden i tabellen ovenfor; bestyrelsen kan gennemgå listen under Indstillinger → Rettigheder.
- **Afklares:** hvilke af disse skal være admin-opgaver: spærre/genåbne et login og sende nyt login-link, slette/anonymisere medlemmer (GDPR), se hele ændringsloggen, klubindstillinger (navn, logo, kontaktoplysninger, sæsondatoer), opsætning af betaling og e-mail, import/eksport af medlemsdata? Forslag: admin er en teknisk rolle, mens priser, pakker og kategorier forbliver bestyrelsens.


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

- **Kerne:** faste aktiviteter: samme ugedag og tidspunkt hver uge (fx hver onsdag kl. 18–20) fra en startdato til og med en slutdato, højst 60 gange. Ugedag og tidspunkt tages fra starttidspunktet. Hver gang bliver sin egen aktivitet med egen tilmelding, deltagerliste og fremmøde. En fast aktivitet har ingen svarfrist og oprettes uden træner – trænerne melder sig selv på de enkelte gange bagefter. Medlemmer, der følger kategorien, får én samlet besked om den faste aktivitet.

- **Kerne:** aktiviteter (også faste) kan oprettes uden træner/tovholder. De vises med mærket "Mangler træner" for trænere og bestyrelse.

  - Dvs også oprette aktiviteter uden at sætte træner/tovholder på. /Thomas

- **Kerne:** aflyses en aktivitet, som ingen er tilmeldt, arkiveres den: den vises ikke som aflyst på aktivitetslisterne, men kan stadig åbnes via et direkte link. Aktiviteter med tilmeldte vises fortsat som aflyst, og deltagerne får besked.

- **Afklares:** hvad sker der, hvis en aktivitet stadig mangler træner kort før start? Fx påmindelse til alle trænere 3 dage før, besked til bestyrelsen dagen før, eller automatisk aflysning med besked til de tilmeldte.

- **Afklares:** skal man kunne ændre alle gange i en fast aktivitet på én gang (fx nyt tidspunkt eller sted fra en bestemt dato)? I proof of concept rettes hver gang for sig, mens trænertilmelding og aflysning kan gælde resten af serien.

- **Senere:** kopi af tidligere aktivitet.

- **Senere:** forudsætninger (kræver fx duelighedsbevis eller eget udstyr), så systemet kan advare ved tilmelding.

- **Senere:** aflysning med begrundelse, som automatisk giver besked og refunderer betaling.

- **Senere:** vejrnote eller "afgøres dagen før", fordi mange aktiviteter afhænger af vind.

**Trænerskift og historik**

- **Kerne:** alle trænere kan ændre, hvem der er træner på en aktivitet.

- **Kerne:** alle trænere kan selv melde sig som træner på en aktivitet ("Jeg tager den") eller sætte en anden træner på ("Sæt anden træner på"). Det sker for én gang ad gangen, også på faste aktiviteter.

- **Kerne:** en træner kan melde fra som træner (én gang eller resten af en fast aktivitet). Står aktiviteten derefter uden træner, får de øvrige trænere besked ("Mangler træner – kan du tage den?").

- **Kerne:** alle trænere kan aflyse en aktivitet med begrundelse; på en fast aktivitet enten kun den ene gang eller denne og alle senere gange. De tilmeldte får besked.

- **Kerne:** Trænerportalen har listen "Mangler træner" med alle kommende aktiviteter uden træner.

- **Afklares:** må alle trænere tage enhver aktivitet, eller kun aktiviteter i de kategorier, de kan undervise i (se Senere-punktet under Trænerportal)? Og skal bestyrelsesmedlemmer, der ikke er trænere, også kunne melde sig som træner/tovholder?

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

- **Kerne:** trænere på en aktivitet tilmeldes ikke selv som deltagere. De kan dog tilmelde deres familiemedlemmer.

- **Kerne:** trænere kan tilmelde sig aktiviteter som almindelige medlemmer (knappen "Tilmeld mig som deltager"), men ikke aktiviteter, hvor de selv er træner. Melder en træner sig som træner på en aktivitet, hvor vedkommende er tilmeldt, afmeldes deltagelsen.

- **Afklares:** må trænere deltage gratis i andre træneres aktiviteter, eller betaler de som alle andre? I proof of concept betaler de som alle andre.

- **Afklares:** hvad sker der med betalingen, hvis man melder fra? Refunderes ved frameldingen før en bestemt frist, ellers ikke? (se afsnittet om betaling)

## Trænerportal og tilgængelighed

- **Kerne:** alle træneren ser en oversigt over aktiviteter, hun/han står for, både kommende og afholdte, med deltagerliste og fremmøde.

- **Kerne:** træneren kan se historikken for, hvem der har været træner på hver aktivitet, hvis der er sket ændringer siden oprettelsen.

- **Kerne:** træneren kan angive dage eller perioder, hvor hun/han ikke er til rådighed (ferie, arbejde, skade).

- **Kerne:** nye aktiviteter oprettes fra Trænerportalen, som også bestyrelsen har adgang til.

- **Kerne:** trænere og bestyrelse kan se en samlet kalender over, hvem der er til rådighed hvornår, når nye aktiviteter skal planlægges.

  - Dage med en aktivitet uden træner er markeret med rødt i datokolonnen (med antal); tryk viser hvilke.
  - Kalenderen viser én måned ad gangen med trænerne som kolonner og dagene som rækker. Den åbner på aktuel måned ved dags dato; knapper over oversigten skifter til forrige/næste måned. Navnerækken står fast, og mandage viser ugenummer.

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

- **Kerne:** alle aktive medlemmer kan lægge opslag op med tekst, op til 10 billeder og 3 videoer, synes godt om (hjerte) og kommentere. Man kan slette sine egne opslag og kommentarer.

- **Kerne:** ved upload af billeder skal man bekræfte, at de afbildede er okay med at blive vist. Billeder skaleres ned, og metadata (fx GPS-position) fjernes automatisk. Billeder kan kun ses af indloggede medlemmer.

- **Kerne:** alle kan anmelde et opslag med en begrundelse. Trænere og bestyrelse får besked og kan skjule opslaget (forfatteren får besked med begrundelsen), vise det igen eller afvise anmeldelsen. Moderatorer kan også skjule enkelte kommentarer. Alt logges.

- **Senere:** opslag knyttet til en aktivitet (fx billeder fra kurset), fastgjorte opslag fra bestyrelsen, push-besked ved nye kommentarer.

- **Kerne (afprøves):** et opslag kan have højst **10 billeder** og **3 videoer** (hver video: MP4, MOV eller WebM, højst 100 MB og 5 minutter). Videoer og billeder vises i samme galleri og i lysbilledvisningen, hvor man kan bladre og afspille videoerne. Videoen uploades med en statuslinje og komprimeres i baggrunden til højst 1280 px, 30 billeder/sek. og H.264 (typisk få MB pr. minut); metadata som GPS-position fjernes, og der laves et stillbillede. Indtil den er klar, står der "Videoen behandles …". Kan den ikke behandles, får forfatteren besked.

- **Afklares:** skal video være tilladt fast, og er 100 MB / 5 minutter de rigtige grænser? Videoer fylder mere på serveren end billeder.

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

- **Kerne:** alle medlemskaber og tilkøb er **pakker**, som bestyrelse og admin selv opretter under Indstillinger → Medlemskaber og tilkøb. En pakke beskriver:
  - **hvem** den dækker: antal personer i alt (mindst–højst) og pr. personkategori (fx "2 voksne og 1–3 børn" eller "1 voksen og 1 barn"),
  - **hvor længe** den gælder: faste datoer hvert år (fx 1/5–30/4 eller 1/8–30/4), et antal dage fra køb (1–365) eller engangsbetaling,
  - **pris** med historik ("gælder fra"), hvilken konto indtægten bogføres på, og om medlemmerne selv kan vælge den.
  - Medlemskaber kan give adgang til aktiviteter eller ej (støttemedlem). Tilkøb kan kræve gyldigt medlemskab.
  - Pakker slettes aldrig, men arkiveres. Ændringer gælder kun nye køb; eksisterende køb beholder personer og periode.

- **Kerne:** personkategorierne vedligeholdes samme sted (i dag *Barn 0–17* og *Voksen 18+*). Alderen regnes på den dag, pakken begynder at gælde.

- **Kerne:** gyldigt medlemskab = personen er med i et betalt medlemskab, der dækker dags dato. En person kan kun have ét medlemskab ad gangen.

- **Kerne:** de hidtidige medlemstyper og priser er lagt ind som pakker: enkelt medlem (hel/halv sæson), husstand med 2 personer (hel/halv sæson), familie med 3, familie med 4 eller flere, og støttemedlem. Udstyrsleje, opbevaring, dagsleje og depositum er tilkøb. Demodata har desuden eksemplerne "Familie: 2 voksne og 1–3 børn" og "Adgang til motionsrum, 30 dage".

- **Kerne:** bestyrelse, kasserer og admin kan lægge en ny pris ind på en pakke med virkning fra en valgt dato (i dag eller senere – aldrig bagud). Den gamle pris bevares som historik, regninger der allerede er sendt ændres ikke, og en kommende pris kan fortrydes, indtil den træder i kraft. Alle ændringer skrives i ændringsloggen.

- **Kerne:** ved indmeldelse vælger man selv medlemskab blandt de pakker, der er til salg nu. Senere køber husstandens betaler medlemskab til nye personer under Profil → Familie. Kommer der en person til midt i perioden, købes en ekstra pakke til vedkommende. Nye personer oprettes først, når der er betalt.

- **Kerne:** kassereren fornyer medlemskaberne for en ny sæson med ét tryk: medlemskaber med faste datoer fornyes med samme personer og samme pakke, eller den pakke, den er sat til at fornyes som (fx halv sæson → hel sæson). Passer personerne ikke længere (fx et barn, der er blevet voksen), eller er pakken arkiveret, vises husstanden på en liste, så kassereren kan tage sig af den.

- **Kerne:** betalingsstatus pr. medlem og sæson: ikke opkrævet, opkrævet, betalt, forfalden.

- **Kerne:** halv sæson er en pakke med faste datoer 1/8–30/4, som kan købes fra 1. august.

- **Kerne:** et medlemskab til flere personer koster aldrig mere end summen af det billigste enkeltmedlemskab, hver person kunne købe samme dag (med samme adgang til aktiviteter). Fx koster "Familie med 3 personer" i november 1.200 kr. (3 × halv sæson 400 kr.) i stedet for 2.500 kr. Reglen gælder også ved fornyelse, men slår sjældent igennem ved sæsonstart, hvor familiepriserne er billigst. Medlemmet ser både den normale og den nedsatte pris, og regningen viser "højst N × enkeltkontingent". (Besluttet 11. nov. 2026.)

- **Kerne:** en pakke kan have en salgsperiode (fx 1. august – 31. oktober), så den kun tilbydes i en del af året. Fornyelse ved ny sæson sker uanset salgsperioden. (Besluttet 11. nov. 2026.)

- **Kerne:** en pakke med faste datoer kan have en **sæsonafslutning**. Fra den dato sælges den til næste periode i stedet for den igangværende. Med sæsonafslutning 1. november gælder for medlemskaberne:
  - maj–juli: hel sæson til 30. april,
  - august–oktober: halv sæson (1/8–30/4),
  - november–april: hel sæson fra næste 1. maj (ingen halv sæson efter sæsonafslutning). Det gælder også familie og støttemedlem.
  - Medlemmet ser "Begynder 1. maj (næste sæson)" ved pakken, og profilen viser "Medlem fra 1. maj". Bestyrelsen kan ændre datoen pr. pakke. (Besluttet 11. nov. 2026; datoen 1. november er et forslag.)

- **Kerne:** den, der melder sig ind efter sæsonafslutning, kan deltage i aktiviteter fra indmeldelsen og frem til sæsonstart (klubbens praksis). Det er en indstilling på hver pakke ("Nye medlemmer kan deltage i aktiviteter fra købsdatoen"), som er slået til. Beslutter bestyrelsen noget andet, slås den fra på pakken, så nye medlemmer først kan deltage fra sæsonstart. (Besluttet 11. nov. 2026.)

- **Senere:** hvis der kommer flere vinteraktiviteter, kan den enkelte aktivitet markeres "kun for medlemmer i den igangværende sæson", så man kan skelne pr. aktivitet i stedet for pr. pakke.

- **Afklares:** hvilken dato er sæsonafslutning? I proof of concept er den 1. november.

- **Kerne:** felterne ved indmeldelse og prøvetræning er markeret, så browseren kan udfylde navn, e-mail, telefon, fødselsdato og adresse automatisk. Felterne til andre personer udfyldes ikke automatisk.

- **Kerne:** ved indmeldelse spørges der først, om andre i husstanden også skal være medlemmer. Svarer man nej, vises kun medlemskaber, der passer til én person. Svarer man ja, tilføjer man personerne og får derefter vist de medlemskaber, der passer, med det billigste først.

- **Senere:** opgradering med modregning (fx fra "1 voksen og 1 barn" til en familiepakke midt i sæsonen, hvor det betalte trækkes fra). Indtil videre købes en ekstra pakke til den nye person.

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



- **Kerne:** tilkøb er pakker (se Kontingent og medlemstyper): bestyrelse og admin opretter dem med navn, pris, periode (faste datoer, antal dage eller engang), hvor mange personer de dækker, og om de kræver medlemskab – fx "Adgang til motionsrum, 30 dage".

- **Kerne:** medlemmet kan tilvælge produkter i profilen og se, hvad det koster.

- **Kerne:** hvert tilkøb bliver en regning med betalingsstatus, på samme måde som kontingent.

- **Kerne:** udstyrsleje findes som pakker for 1, 2, 3, 4 og 5 personer i husstanden (samme priser som før). Lejen gælder sæsonen ud (30. april).

- **Senere:** nye tilkøb med pladser (som rack og skab) kræver, at pladserne kan oprettes under Indstillinger. Indtil videre har kun de eksisterende opbevaringspakker pladser.

- **Senere:** tilkøb med faste datoer (fx opbevaring) fornyes automatisk ved ny sæson ligesom medlemskaber.

- **Afklares:** priserne for opbevaring (rackplads 600 kr., skab 400 kr.) og dagsleje (100 kr.) er foreløbige. Bestyrelsen kan selv rette dem under Indstillinger.

- **Kerne:** tilbud til ikke-medlemmer er pakker af typen **tilbud** (tilmelding til én aktivitet). Bestyrelsen opretter og retter dem under Indstillinger → Medlemskaber og tilkøb → **Ikke-medlemmer** (navn, beskrivelse, pris med "gælder fra", konto, og om beløbet modregnes i kontingentet). Fanebladet viser også klippekort kun for ikke-medlemmer. På aktiviteten vælger træneren under "Åben for ikke-medlemmer", hvilket tilbud den er åben for.

- **Kerne (i proof of concept):** ikke-medlemmer kan på siden "Prøv SurfHorsens" købe klubbens tilbud, i dag en **prøvetræning (400 kr.)** eller et **SUP-kursus (500 kr.)**. De vælger en dato blandt de aktiviteter, trænerne har åbnet for gæster, udfylder navn, kontaktoplysninger og fødselsdato (under 18: forælders navn og telefon) og betaler med MobilePay. De står derefter på deltagerlisten mærket "Gæst", men har ikke adgang til medlemsdelen.

- **Kerne (i proof of concept):** melder gæsten sig ind inden for 12 måneder (med samme e-mail), trækkes det betalte fra kontingentet – for de tilbud, der er sat til at blive modregnet. Dækker beløbet hele kontingentet, er man medlem med det samme uden yderligere betaling.

- **Afklares:** hvis gæstekøbet er større end kontingentet (fx SUP-kursus 500 kr. og halv sæson 400 kr.), bortfalder resten i proof of concept. Skal det overskydende kunne bruges til fx udstyrsleje?

- **Afklares:** skal medlemmer også betale for SUP-kurset (i proof of concept: 500 kr. for alle), og skal en prøvetræning/et kursus kun modregnes inden for 12 måneder?

- **Afklares:** hvor længe gemmes oplysninger om gæster, der ikke melder sig ind?

**Klippekort for ikke-medlemmer** (fx SUP 10-turskort)

- **Kerne:** et klippekort er et tilkøb med et antal klip (fx 10), der gælder 12 måneder fra køb og til aktiviteter i bestemte kategorier (fx SUP). Bestyrelsen opretter det under Indstillinger → Medlemskaber og tilkøb (felterne "Klippekort med klip" og "Kun for ikke-medlemmer"). Klippekort er kun for ikke-medlemmer. (Besluttet 11. nov. 2026.)

- **Kerne:** ikke-medlemmer køber kortet og opretter et login på siden /klippekort (linket fra login-siden) og betaler med MobilePay. De logger ind som gæst og ser kun deres klippekort, de aktiviteter, kortet gælder til, og deres egne regninger.

- **Kerne:** en aktivitet åbnes for klippekort med feltet "Åben for klippekort" (også på faste aktiviteter). Feltet vises kun, når der findes et klippekort til en af aktivitetens kategorier. Tilmelding bruger ét klip; afmelding inden afmeldingsfristen giver klippet tilbage, udeblivelse gør ikke. Trænerne ser gæsterne på deltagerlisten med mærket "Gæst" og kan registrere fremmøde som for alle andre. Klubben kan dermed se, hvor mange der kommer.

- **Kerne:** papkort: den, der allerede har betalt for et papkort, opretter kun et login på /klippekort. Kassereren eller bestyrelsen overfører derefter de resterende klip under Medlemmer → gæstens side ("Overfør papkort"); kortet gælder 12 måneder fra overførslen.

- **Afklares:** prisen på SUP 10-turskortet. I proof of concept er den foreløbigt 600 kr. Bestyrelsen kan rette den under Indstillinger.

- **Afklares:** skal et betalt klippekort trækkes fra kontingentet, hvis gæsten melder sig ind (som prøvetræning og SUP-kursus inden for 12 måneder)? I proof of concept trækkes det ikke fra.

- **Afklares:** må en gæst tage en ven med på sit kort (2 klip til samme træning), eller er kortet personligt? I proof of concept er det personligt.

- **Afklares:** hvor længe gemmes oplysninger om gæster med klippekort, når kortet er brugt op eller udløbet (se også punktet ovenfor om gæster)?

- **Kerne:** opbevaringspladser og lejeudstyr registreres, så samme plads eller samme bræt ikke bliver udlejet to gange.

- **Senere:** kalender for udstyrslejen med booking pr. dag og tid, samt retur-registrering.

  - Forslag: oversigt over udstyr og tilstand. /Thomas

- **Senere:** ventelister på opbevaringspladser.

- **Afklares:** er det kun medlemmer, der kan leje, eller også gæster? Skal der være depositum?

## Udstyr og booking

Oversigten viser, hvad klubben har af udstyr, og i hvilken stand det er. Den kan ikke forventes at være 100 % opdateret i en forening med mange brugere, men en booking angiver altid præcis, hvilket udstyr der bookes.

- **Kerne:** udstyr er ordnet i **underkategorier** under aktivitetskategorierne, fx Windsurf → Boards, Sejl, Master, Bomme og SUP → Boards, Padler. Administrator opretter og retter underkategorier (rettigheden "Oprette og rette underkategorier af udstyr").
- **Kerne:** trænere og bestyrelse vedligeholder listen under **Udstyr**: tilføjer udstyr (også flere ens på én gang, fx SUP 1–10), retter navn og bemærkning og sætter stand: **OK**, **Skal serviceres** (kan stadig bruges og bookes) eller **Defekt** (kan ikke bookes eller bruges). Hver ændring gemmes med hvem, hvornår og en bemærkning. Meldes noget defekt, får dem, der har booket det, besked.
- **Kerne:** kasseret udstyr arkiveres. Det forsvinder fra listen og kan ikke bookes, men historikken bevares.
- **Kerne:** en aktivitet booker som udgangspunkt **alt udstyr i underkategorierne til aktivitetens kategorier** (en windsurf-aktivitet booker alle boards, sejl, master og bomme). Træneren kan i stedet vælge **kun bestemt udstyr** – så kan resten bookes af andre.
- **Kerne:** er noget af udstyret allerede booket, når en aktivitet oprettes eller flyttes, vises hvad og af hvem, med knapper til at skrive til den, der har booket. Træneren kan vælge: opret alligevel, ret tidspunkt/udstyr, eller opret som **Afventer**.
- **Kerne:** en aktivitet, der **afventer**, er ikke rigtig oprettet endnu: medlemmerne kan ikke se den eller tilmelde sig, de får ikke besked, og den optager ikke udstyr. Trænere ser den med mærket "Afventer" og opretter den på aktivitetens side ("Opret aktiviteten nu"), når det er afklaret. Ved faste aktiviteter afventer kun de gange, hvor udstyret er booket.
- **Kerne:** trænere, bestyrelse og medlemmer med betalt **udstyrsleje eller dagsleje** (pakker med "Giver ret til at booke udstyr", gælder i pakkens periode, når regningen er betalt) kan booke. Har man købt leje, der ikke er betalt, ser man Udstyr med en "Betal nu"-knap. Man kan booke bestemte stykker udstyr i et tidsrum (højst 14 dage). Udstyr, som en anden har booket, kan ikke vælges.
- **Kerne:** vil man booke udstyr, som en aktivitet har booket, sendes et **ønske** til træneren/tovholderen (ellers den, der oprettede aktiviteten), som får besked og svarer **Ja** eller **Nej** med én knap – på aktivitetens side eller under Udstyr → Bookinger. Ved ja er udstyret lånt ud og ikke længere booket af aktiviteten. Den, der ønskede, får besked om svaret. Bestyrelsen kan også svare (rettigheden "Svare på ønsker om udstyr på alle aktiviteter").
- **Kerne:** har et medlem booket udstyr, som en aktivitet skal bruge, kan træneren **bede om at få bookingen flyttet** – direkte fra advarslen, når aktiviteten oprettes eller rettes (med en valgfri besked), eller senere på aktivitetens side. Medlemmet får besked med en **svarfrist** (24 timer, dog højst halvdelen af tiden til aktiviteten starter) og kan under Udstyr → Bookinger **flytte bookingen** (nyt tidspunkt eller andet udstyr), **annullere** den eller **sige nej** med en begrundelse. Træneren får besked om svaret, og når udstyret er frigivet.
- **Kerne:** påmindelser (sendes én gang): den, der har booket, får en påmindelse 3 timer før svarfristen, og træneren får besked, når fristen er overskredet uden svar ("Bookingen kan nu annulleres"). Må ingen af dem annullere, går beskeden til bestyrelsen.
- **Kerne:** en aktivitet, der stadig **afventer**, giver træneren besked dagen før start ("Opret eller aflys den") og bestyrelsen besked 3 timer før start. Flyttes aktiviteten, sendes påmindelserne igen.
- **Kerne:** svarer medlemmet ikke inden fristen, kan træneren/tovholderen på aktiviteten (eller den, der bad om det) **annullere bookingen** på aktivitetens side – hvis de har rettigheden "Annullere en booking, når fristen for at flytte den er overskredet". Admin vælger under Rettigheder, om det gælder trænere eller kun bestyrelsen (standard: trænere, bestyrelse og admin). Medlemmet får besked om hvorfor. Har medlemmet sagt nej, kan kun bestyrelsen annullere bookingen (rettigheden "Annullere andres bookinger af udstyr", som også kan annullere når som helst).
- **Kerne:** under Udstyr → Bookinger ser man sine egne bookinger, ønsker og anmodninger om at flytte og kan annullere dem. Trænere og bestyrelse kan se alle kommende bookinger.
- **Senere:** retur-registrering og skadesmelding direkte fra aktiviteten (træneren markerer udstyr, der trænger til reparation).
- **Afklares:** skal en booking kunne vare mere end 14 dage (fx til en lejr)?
- **Afklares:** demo-listen over udstyr er et eksempel. Hvem lægger klubbens rigtige udstyr ind, og skal hvert stykke have et fysisk nummer/mærkat?

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

- **Kerne:** **udlæg**: alle medlemmer kan under Min profil → fanen "Kvitteringer" sende et billede eller en PDF af en kvittering til kassereren med en beskrivelse og beløbet. Medlemmet vælger, om pengene skal udbetales via MobilePay (telefonnummeret fra profilen foreslås, men kan rettes) eller til en bankkonto (reg. nr. og kontonummer). Store billeder skaleres ned før upload, så teksten stadig kan læses. Medlemmet kan følge status (afventer, udbetalt, afvist).

  - Økonomi findes kun på web. Trykker man i appen på en besked med link til Økonomi (fx et nyt udlæg), åbnes siden i browseren.

  - Kassereren ser udlæggene under Økonomi → Udlæg, overfører pengene manuelt (MobilePay/netbank) og bogfører derefter udlægget på en udgiftskonto. Kvitteringen bliver bilag på posteringen. Kassereren kan også afvise med en begrundelse. Medlemmet får besked i begge tilfælde, og kassereren får besked, når der kommer et nyt udlæg.

- **Afklares:** skal udlæg være aftalt på forhånd (fx med bestyrelsen eller den ansvarlige for et budget), og er der en frist for at indsende kvitteringer (fx 3 måneder eller inden regnskabsårets afslutning)?

- **Kerne:** kassererens egne udlæg skal godkendes af revisoren, før kassereren kan bogføre og udbetale dem. Revisoren får besked og kan godkende eller afvise under Økonomi → Udlæg. Ingen kan godkende eller afvise sine egne udlæg. (Besluttet 11. nov. 2026.)

- **Senere:** udbetaling af udlæg direkte fra systemet i stedet for manuelt i MobilePay/netbank. MobilePays udbetalingsfunktion kan kun sende penge tilbage til en betaling, medlemmet tidligere har lavet til klubben, og kræver en særskilt aftale, så den egner sig formentlig ikke til udlæg. Mere realistisk er en betalingsfil, som kassereren uploader i bankens netbank. Det skal undersøges med klubbens bank.

- **Afklares:** hvor længe gemmes medlemmers bankoplysninger fra udlæg? Kvitteringen er bilag og skal gemmes i 5 år efter bogføringsloven, men kontonummeret kunne slettes, når udlægget er udbetalt.

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

