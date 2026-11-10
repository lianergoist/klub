# Økonomidelen – hvad den kan, hvad den ikke kan, og hvornår I bør bruge et regnskabsprogram

Dette er et beslutningsgrundlag for bestyrelsen. Det beskriver økonomidelen i klubsystemet, som den er i proof of concept og som den er planlagt i [funktionsoversigten](Funktionsoversigt.md), og hvornår det er klogt eller nødvendigt at føre regnskabet i et eksternt regnskabsprogram i stedet.

> **Forbehold:** Afsnittene om lovkrav (bogføringsloven, moms, indberetning af honorarer) er en orientering, ikke rådgivning. Hvilke regler der gælder for netop jeres forening, afhænger af fx om I har lønnede trænere, sælger varer eller tjenester, og hvilke tilskud I modtager. Få det bekræftet af jeres revisor, før I beslutter jer.

## Kort fortalt

Klubsystemet er stærkt til det, der er **særligt for en forening**: opkrævning af kontingent, betaling for aktiviteter og tilkøb, hvem der har betalt hvad, og at hver betaling automatisk bliver bogført. Det giver kassereren et enkelt overblik og fjerner det meste af det manuelle arbejde.

Det er **ikke et fuldt regnskabsprogram**. Det fører et enkelt indtægts- og udgiftsregnskab, ikke dobbelt bogholderi, og det kan ikke moms, løn, afskrivninger eller automatisk bankforbindelse.

For en lille frivillig klub uden moms og løn er det sandsynligvis nok. Så snart klubben får moms, lønnede trænere eller en vis størrelse, bør bogføringen ske i et regnskabsprogram, mens klubsystemet fortsat står for opkrævning og betaling og sender betalingerne videre.

## Hvad økonomidelen kan

### Opkrævning og betaling (det, klubsystemet er bygget til)

| Funktion | I proof of concept | Bemærkning |
|---|---|---|
| Kontingent pr. medlemstype, familiekontingent, forholdsmæssigt kontingent | Ja | Indmeldelse kræver betaling; skift til familie opkræver forskellen |
| Opkrævning af kontingent for et helt år til alle medlemmer | Ja | Én knap; én regning pr. medlem/husstand |
| Betaling for aktiviteter og tilkøb (leje, opbevaring, depositum) | Ja | Pladsen reserveres, mens man betaler |
| MobilePay og kort | Simuleret | Vipps MobilePay-aftale kræves i drift |
| Manuel registrering af kontant- og bankbetaling | Ja | |
| Refusion (hel/delvis) med godkendelse og log | Ja | |
| Påmindelse om forfaldne betalinger | Manuel | Automatiske rykkere er planlagt (Senere) |
| Afstemning af MobilePay-udbetalinger | Ja (simuleret rapport) | Afregningsrapporten hentes hver morgen: betalinger kobles til den samlede udbetaling, og gebyrer bogføres automatisk. Bankafstemningen genkender udbetalingen og afstemmer alle betalinger i den på én gang. I drift hentes rapporten fra Vipps MobilePays rapport-API. |
| Overblik over åbne, betalte og forfaldne regninger | Ja | Pr. medlem, pr. type |

### Bogføring og overblik

| Funktion | I proof of concept | Bemærkning |
|---|---|---|
| Automatisk bogføring af alle betalinger | Ja | Kassereren skal kun taste det, der ikke kommer af sig selv |
| Manuelle posteringer med bilag (foto/PDF) | Ja | |
| Rettelse ved modpostering – intet slettes | Ja | |
| Enkel kontoplan (indtægter/udgifter), som bestyrelsen kan udvide | Ja | |
| Økonomi pr. aktivitet | Ja | |
| Budget og sammenligning med faktisk resultat | Ja | |
| Bankafstemning ud fra CSV-udtræk fra netbanken | Ja | Matcher på beløb og dato |
| Årsregnskab til generalforsamlingen (resultat + enkel balance), PDF | Ja | |
| Læseadgang for revisor, ændringslog | Ja | |
| Eksport af posteringer til regneark (CSV) | Ja | Kan også bruges til at overføre til et regnskabsprogram |
| Udlæg (træner/bestyrelse har lagt ud) med godkendelse og udbetaling | Nej | Kerne i funktionsoversigten, ikke bygget endnu |

## Hvad økonomidelen ikke kan

| Begrænsning | Hvad det betyder i praksis |
|---|---|
| **Ikke dobbelt bogholderi** | Hver postering står på én konto (indtægt eller udgift), og saldoen er summen af posteringerne. Der er ingen rigtige balancekonti (bank, kasse, MobilePay-tilgodehavende, skyldige beløb), og balancen i årsregnskabet er forenklet. En revisor vil typisk forvente dobbelt bogholderi, når regnskabet bliver større. |
| **Ingen moms** | Ingen momskoder, intet momsregnskab, ingen momsindberetning. |
| **Ingen løn eller honorarer** | Kan ikke beregne skat og AM-bidrag eller indberette til eIndkomst. |
| **Ingen automatisk bankforbindelse** | Kontoudtog skal hentes som CSV og uploades. Regnskabsprogrammer kan typisk hente bankposteringer automatisk. |
| **Ingen periodisering eller afskrivninger** | Fx kontingent betalt i december for næste år, eller nyt udstyr der skal afskrives over flere år. |
| **Ingen e-faktura** | Kan ikke sende eller modtage elektroniske fakturaer (fx til og fra kommunen). |
| **Ingen godkendt standard** | Systemet er ikke anmeldt som "digitalt standard bogføringssystem" efter bogføringsloven. Det kræver bl.a. godkendelse hos Erhvervsstyrelsen og vil være et større projekt. |

## Hvornår et eksternt regnskabsprogram er tilrådeligt eller nødvendigt

### Sandsynligvis **nødvendigt**, hvis et af disse gælder

- **Klubben skal momsregistreres**, fx fordi salg af varer eller tjenester, der ikke er momsfritaget som idræt, overstiger grænsen for momsregistrering. Det kan fx være klubtøj, udlejning til ikke-medlemmer, events eller sponsorydelser.
- **Klubben udbetaler løn eller skattepligtigt honorar** til trænere eller andre, som skal indberettes til eIndkomst. Skattefri godtgørelser til frivillige inden for de gældende grænser kræver det typisk ikke, men det skal afklares.
- **Klubben er omfattet af bogføringslovens krav om digital bogføring**, fx fordi den driver erhvervsmæssig virksomhed. For de fleste frivillige idrætsforeninger gælder kravene ikke fuldt ud, men det bør revisor bekræfte.
- **Kommune, forbund eller fonde stiller krav** til regnskabets form, som klubsystemets forenklede regnskab ikke kan opfylde.

### **Tilrådeligt**, hvis et af disse gælder

- Revisor beder om dobbelt bogholderi eller en rigtig balance.
- Der er mange posteringer (fx flere hundrede om måneden) eller flere bankkonti.
- Klubben har væsentlige aktiver (både, container, trailer), der bør afskrives.
- Kassereren eller revisor kender i forvejen et regnskabsprogram og foretrækker det.
- I vil have automatisk bankforbindelse og færre manuelle afstemninger.

### **Ikke nødvendigt**, hvis alt dette gælder

- Ingen moms, ingen løn og ingen erhvervsmæssig aktivitet.
- Et overskueligt antal posteringer, hvor langt de fleste er kontingent og aktiviteter, som bogføres automatisk.
- Revisor er tilfreds med et indtægts- og udgiftsregnskab med bilag, bankafstemning og ændringslog.

## Anbefalet løsning, hvis I vælger et regnskabsprogram: arbejdsdeling

Klubsystemet og regnskabsprogrammet skal ikke konkurrere. De bør dele arbejdet:

| Klubsystemet | Regnskabsprogrammet |
|---|---|
| Medlemmer, kontingent, regninger, betalinger, refusioner | Selve bogføringen (dobbelt bogholderi) |
| Hvem har betalt hvad, rykkere, afregning af MobilePay-udbetalinger | Bank og afstemning |
| Udlæg fra trænere (indsendelse og godkendelse) | Moms, løn/honorar, afskrivninger |
| Økonomi pr. aktivitet, budget for bestyrelsen | Årsregnskab og revision |

Betalinger kan sendes fra klubsystemet til regnskabsprogrammet automatisk, hvis programmet har en åben programmeringsflade (API). Det har de udbredte danske programmer typisk, fx Dinero og e-conomic. Alternativt kan de sendes som en samlet fil pr. måned. Kassereren skal så ikke taste kontingent og aktivitetsbetalinger to steder.

Bemærk også: Der findes færdige foreningssystemer på markedet (fx Conventus, Klubmodul og ForeningLet), som dækker medlemmer, kontingent og ofte også regnskab. Hvis bestyrelsen alligevel overvejer et eksternt program, er det værd at sammenligne med dem, før I beslutter, hvor meget der skal bygges selv.

## Spørgsmål til bestyrelsen

Disse står også som **Afklares** i funktionsoversigten:

1. Skal bogføringen ske i klubsystemet eller i et eksternt regnskabsprogram, som klubsystemet sender betalinger til?
2. Har klubben, eller forventer den at få, momspligtigt salg eller lønnede trænere?
3. Hvad kræver revisor og eventuelle tilskudsgivere af regnskabets form og opbevaring af bilag?
