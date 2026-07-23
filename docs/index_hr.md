# Vodič za Workspace modul

English version: [index_en.md](index_en.md)

## 1. Mentalni model

Područje je organizacijska i sigurnosna granica iznad pojedinih stranica.
Područje je vlasnik stabla stranica i prava, dok specijalizirani moduli ostaju
vlasnici svojeg sadržaja.

Primjerice, čvor dokumenta sprema `document_key` HTML editora, a HTML editor
i dalje sprema HTML verzije i metapodatke privitaka. Čvorovi linkova sadrže
internu rutu/putanju ili vanjski HTTPS URL i ne ovise o editoru.

Takav dizajn sprječava Workspace modul da izravno čita privatne tablice drugog
modula.

## 2. Model podataka

Jedina inicijalna migracija kroz ORM kreira pet tablica:

| Tablica | Odgovornost |
| --- | --- |
| `workspaces` | Identitet, slug, vidljivost, vlasnik, arhiva i soft delete |
| `workspace_acl` | Prava korisnika/grupa na razini Područja |
| `workspace_nodes` | Uređena hijerarhija dokumenata i linkova |
| `workspace_node_acl` | Dodatna ograničenja naslijeđena kroz stablo |
| `workspace_node_workflows` | Stanje objave i pokazivači na nepromjenjive Editor verzije po stranici i jeziku |

Nema SQL-a vezanog uz određenu bazu. Boolean zadane vrijednosti su stvarni
booleani, a shema je kompatibilna sa SQLite, PostgreSQL i MySQL/MariaDB bazama.

Migracija namjerno ne sadrži testne podatke.

## 3. Vidljivost Područja

Vidljivost se podešava u istoj ACL tablici kao i ostala prava. Dvije ugrađene
publike izgledaju kao grupe, ali nisu Auth grupe i ne stvaraju se u bazi
korisničkih grupa:

- **Javno** (`public`) obuhvaća goste i prijavljene korisnike te uvijek daje
  isključivo pregled. Dodavanje, uređivanje, brisanje i upravljanje za tu su
  publiku namjerno onemogućeni.
- **Svi prijavljeni** (`authenticated`) obuhvaća svakog prijavljenog korisnika
  i može dobiti pregled te šira prava koja odabere upravitelj Područja.
- Kada nije dodana nijedna ugrađena publika, Područje je `restricted` i vide ga
  samo vlasnik, administratori te izričito ovlašteni korisnici ili Auth grupe.

Stupac `workspaces.visibility` čuva sažeto stanje radi brzog filtriranja
Područja, ali se automatski sinkronizira iz ugrađenih ACL redaka. Zato obrazac
nema drugi, odvojeni odabir vidljivosti koji bi se mogao razići s pravima.

Arhivirano Područje ostaje čitljivo ovlaštenim korisnicima, ali su promjene
sadržaja isključene i vlasniku i administratoru. Njihovo `can_manage` pravo
ostaje aktivno kako bi mogli ponovno uključiti Područje. Brisanje Područja je
soft delete. Administratori mogu vidjeti i vratiti obrisana Područja te riješiti
konflikt sluga.

## 4. Prava i nasljeđivanje

Workspace ACL prihvaća pojedinačne korisnike, stvarne Auth grupe te ugrađene
publike `public` i `authenticated`. Prava trenutačnog korisnika, ugrađenih
publika kojima pripada i svih njegovih grupa se zbrajaju:

- `can_view`
- `can_add`
- `can_edit`
- `can_delete`
- `can_manage`

`can_manage` uključuje sva ostala prava. Vlasnik Područja i administratori
aplikacije dobivaju potpuni skup prava.

Ekran ne učitava sve korisnike i grupe. Prikazuje samo već dodijeljene ACL
retke, a novi se subjekt dodaje pretraživačem. Pretraga se izvršava na serveru,
prihvaća dio prikaznog imena, korisničke oznake ili naziva grupe i vraća najviše
20 rezultata po zahtjevu. Prethodni zahtjev prekida se kada korisnik nastavi
pisati. Takav obrazac ostaje uporabiv i kada Auth imenik sadrži tisuće
korisnika i stotine grupa, bez slanja cijelog imenika u HTML.

Promjena vlasnika koristi isti ograničeni pretraživač korisnika. Uklanjanje
retka iz tablice uklanja samo dodjelu prava nakon spremanja; ne briše korisnika
ni grupu iz Auth modula.

ACL čvora namjerno samo ograničava:

1. izračunaju se efektivna prava Područja;
2. prolazi se put od korijenskog do traženog čvora;
3. kada predak ima ograničenja, zadržavaju se samo prava dopuštena već
   ovlaštenom korisniku ili njegovim već ovlaštenim grupama;
4. pravo Područja nikada se ne može proširiti kroz stranicu.

Prazan ACL čvora znači “naslijedi bez dodatnog ograničenja”. Ograničenje
roditeljske stranice automatski vrijedi za sve potomke.

## 5. Stablo stranica

Lijevo stablo može se prikazati ili sakriti. Izgledom prati karticu sadržaja
HTML editora: koristi isti naslov, `list-group`, temu i unutarnji scroll. Na
desktopu ostaje dostupno tijekom čitanja, a na mobitelu postaje ograničen i
sklopiv blok.

Kartica stabla i HTML kartica počinju u istom retku. Prekidač stabla nalazi se
kao prva SVG akcija zajedničkog Editorova pregleda, dok su SVG akcije za novu
stranicu i upravljanje Područjem u zaglavlju samog stabla. Tako akcije ne
rezerviraju zaseban prazan red, a njihova vidljivost i dalje prati efektivni ACL.
Kompaktne akcije zaglavlja poravnate su desno u vlastitom retku, a puni naziv
Područja prikazuje se ispod njih bez skraćivanja.

Vrste čvorova:

- `document`: povezuje jedan dokument HTML editora;
- `internal_link`: vodi na imenovanu projektnu rutu ili internu putanju;
- `external_link`: vodi na provjereni vanjski URL.

Roditelj i redoslijed određuju hijerarhiju. Korisnik s potpunim pravom
upravljanja uključuje organizator izravno ikonom u zaglavlju lijevog stabla:
strelice gore/dolje pomiču cijelu podgranu među stavkama istog roditelja, a
strelice lijevo/desno izvlače ili uvlače podgranu za jednu razinu. Nedostupne
radnje su onemogućene. Brojevi redoslijeda sinkroniziraju se automatski i cijeli
se raspored sprema jednom atomskom ORM transakcijom. Repository prije prvog
zapisa provjerava da su poslani svi aktivni čvorovi, da su roditelji
dokument-stranice i da nema ciklusa.

Mala edit ikona uz stavku otvara Bootstrap modal s naslovom, slugom, vrstom,
ciljem, nasljednim ograničenjima i brisanjem. Obrazac se učitava na zahtjev pa
velika stabla ne stvaraju stotine skrivenih formi. Gumb na dnu organizatora
otvara modal za dodavanje linka ili postojećeg dokumenta i odabir početnog
roditelja. Brisanje čvora soft-briše cijelu podgranu. Povezani editor dokument
soft-briše se kroz opcionalni servisni most. Zaseban ekran “Upravljaj
područjem” zadržava samo podatke područja, članove, Workspace ACL i brisanje
područja.

Premještanje čvora traži `can_edit` na čvoru i `can_add` na novom roditelju
odnosno korijenu. Upravljački prikaz uopće ne šalje korisniku čvorove za koje
nema efektivno `can_view` pravo. Korisnik s pravom izmjene sadržaja vidi
upravljanje stablom, ali podatke i ACL samog Područja može mijenjati samo uz
`can_manage`.

Interni link prihvaća postojeću imenovanu rutu ili lokalnu apsolutnu putanju
koja počinje jednom kosom crtom, primjerice `/calendars`. Lokalna putanja
automatski se smješta ispod aplikacijskog prefiksa pa postaje `/hfc/calendars`
kada je aplikacija instalirana pod `/hfc`. Apsolutni HTTP(S) URL-ovi dopušteni
su samo tipu `external_link`.

Jedan aktivni HTML dokument može pripadati samo jednom aktivnom Workspace
čvoru. Tako su vlasništvo URL-a i ACL-a nedvosmisleni.

## 6. Integracija s HTML editorom

Integracija je opcionalna i izvedena servisima. Workspace dinamički prepoznaje
paket editora i koristi njegove javne servise. Editor na isti način prepoznaje
Workspace ACL bez tvrde Composer ovisnosti.

Kada je Workspace uključen:

- postavke editora pokazuju da Područja upravljaju javnim putanjama;
- samostalni editor slug prekidač je isključen;
- povezani dokument učitava se samo uz nasljedno `can_view` pravo;
- Workspace u desnom stupcu ugrađuje Editorov zajednički potpuni pregled, pa su
  tema, jezici, povijest, privitci, ZIP export, sadržaj dokumenta i audit
  identični samostalnom pregledu;
- uređivanje, upload, metapodaci, verzije i privitci traže `can_edit`;
- brisanje dokumenta traži `can_delete`;
- URL-ovi dokumenata u Menu modulu vode na Workspace stranice.

Workspace ne čita Editorove privatne tablice i ne kopira njegov HTML predložak.
Kroz opcionalni servisni most traži `EditorDocumentViewBuilder`, a zatim
renderira Editorov službeni `editor/view` partial uz lijevo stablo. Jezični i
TOC linkovi zato ostaju u trenutačnom Području, dok export i asset rute ponovno
provjeravaju isti nasljedni ACL na serveru.

Korisnik s pravom `can_add` u otvorenom Području vidi naredbu **Nova
stranica**. Sažeti obrazac traži samo naslov, opcionalni slug i nadređenu
stranicu. Nakon spremanja modul kreira editor dokument, povezuje njegov
stabilni ključ sa stranicom stabla i odmah otvara HTML editor. Prva kreirana
stranica automatski postaje početna stranica Područja.

Kreiranje dokumenta prvo provjerava `can_add` na Području i odabranoj
nadređenoj stranici. Link ne može biti roditelj nove stranice.
Obični urednik može zadržati dokument svojeg čvora ili automatski kreirati novi.
Ne može ručnim POST zahtjevom povezati drugi postojeći dokument. Administrator
može odabrati postojeći dokument iz popisa, a server prije spremanja provjerava
da dokument stvarno postoji i da već ne pripada drugom aktivnom čvoru.

Upravljački ekran nije mjesto za pisanje sadržaja ni slaganje stabla. Stablo,
linkovi i nasljedna ograničenja uređuju se u kontekstu otvorenog Područja.
Napredni modal prikazuje samo polja relevantna odabranoj vrsti stavke.

Bez HTML editora Područja i linkovi i dalje rade. Bez Workspace modula HTML
editor zadržava samostalno ponašanje.

## 7. Proces objave

Stanje objave pripada dokument-čvoru i jeziku. Workspace sprema samo status,
audit vremena, korisničke identifikatore i brojeve Editorovih verzija. HTML
sadržaj, privitci i nepromjenjive verzije i dalje pripadaju HTML editoru.

Čisto početno stanje je **Nacrt**. Dokument-čvor bez workflow retka također se
tretira kao neobjavljeni nacrt; nema starog automatskog objavljivanja.
Podržana stanja su:

1. **Nacrt**: urednici rade na jednom zajedničkom promjenjivom nacrtu.
   Obični pregled svakome pokazuje ranije objavljenu verziju ako ona postoji.
   Nacrt se otvara zasebnim akcijama za uređivanje ili pregled. Obični pregled
   objavljene stranice ne nudi akcije koje mijenjaju nacrt; odbacivanje, slanje
   na pregled i objava dostupni su tek na eksplicitnom pregledu nacrta.
2. **Na pregledu**: radna je verzija spremna za pregled, ali još nije javna.
3. **Objavljeno**: odabrana nepromjenjiva verzija postaje verzija za čitatelje.
4. **Arhivirano**: stranica se uklanja iz stabla i pregleda za čitatelje.
   Vraćanjem nastaje neobjavljeni nacrt koji treba ponovno objaviti.

Spremanje mijenja isti zajednički nacrt i ne dodaje ga u povijest. Povijest
sadrži samo objavljene, nepromjenjive verzije. Vraćanje povijesne verzije,
kopiranje jezika, brisanje privitka ili druga promjena sadržaja također priprema
zajednički nacrt. Time se ne zamjenjuje `published_version_number`, pa svaki
obični pregled dobiva stabilan objavljeni sadržaj dok se priprema sljedeća objava.

Prava namjerno odvajaju uređivanje od objavljivanja:

- `can_edit`: slanje nacrta na pregled i povratak s pregleda;
- `can_publish`: objava nacrta, uključujući izravno `Spremi i objavi`, nakon
  čega se otvara javni pregled objavljene stranice;
- `can_manage`: uključuje sva prava te dodatno arhiviranje i vraćanje;
- svi korisnici na običnom pregledu vide točno zapisanu objavljenu verziju i
  njezin povijesni skup privitaka;
- korisnici s pravom uređivanja ili objavljivanja dobivaju zasebne ikone za
  uređivanje i pregled zajedničkog nacrta.

Prijelazi se provjeravaju na serveru kroz `POST /workspaces/workflow`; izmjena
gumba, URL-a ili request tijela ne može zaobići efektivni nasljedni ACL.
Diskretne workflow akcije prikazuju se samo korisniku koji ih smije izvršiti.
Nove nikad objavljene stranice označene su uz naslov u stablu i dostupne kroz
brojač `Nove neobjavljene stranice`. Korisnici s pravom `can_publish` imaju i
zaseban brojač `Poslano na pregled` s popisom stranica spremnih za objavu.
Odbacivanje nove stranice bez ijedne objave na bilo kojem jeziku trajno briše
njezin Workspace čvor, workflow, ograničenja i Editor dokument s privitcima.
Stranica zato ne završava među soft-obrisanim dokumentima. Ako čvor ima djecu,
ona se premještaju njegovu roditelju. Ova destruktivna inačica odbacivanja traži
efektivno `can_delete` pravo. Ako postoji objava na drugom jeziku, odbacuje se
samo nacrt trenutačnog jezika.

Kada je instaliran opcionalni Notification modul, slanje nacrta na pregled
kreira dedupliciranu inbox poruku svakom efektivnom objavljivaču osim korisniku
koji je izvršio radnju. Objavljivanje šalje poruku korisniku koji je nacrt
poslao kada to nije ista osoba. Obavijesti su pomoćni kanal i njihov neuspjeh
ne može poništiti uspješan workflow prijelaz. Ako je uključen opcionalni E-mail
modul, ista obavijest može se staviti i u njegov trajni SMTP outbox.

Kada Workspace nije instaliran, svi integracijski pozivi su no-op. Samostalno
spremanje, pregled, povijest i export Editora nastavljaju koristiti aktualnu
verziju dokumenta kao i prije.

## 8. Konfiguracija

`config/workspace.php` podržava:

```php
return [
    'enabled' => true,
    'routing' => [
        'root_path' => 'workspace',
    ],
    'defaults' => [
        'visibility' => 'restricted',
        'tree_visible' => true,
    ],
    'creation' => [
        'authenticated_users' => false,
    ],
    'menu' => [
        'auto_register_top' => true,
        'auto_register_settings' => true,
    ],
];
```

Korijenska putanja mora biti slobodan prvi segment rute. Postavke odbijaju
konflikt s postojećom rutom aplikacije.

Ako je Menu uključen, Workspace idempotentno registrira:

- glavnu stavku Područja;
- Opće postavke;
- Sva područja;
- Obrisana područja.

Ponovljeni requesti ne dupliciraju niti premještaju te stavke.
Administratorske stranice Područja prikazuju zajednički lijevi izbornik
Postavki kada je Menu dostupan. Bez Menu modula iste stranice ostaju uporabive
kroz lokalni rezervni izbornik s Općim postavkama, Svim područjima i Obrisanim
područjima.

## 9. Instalacija i rad

```bash
composer require aaieduhr/heartphrame-module-workspace
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

Auth i ORM moraju biti uključeni prije Workspace paketa. Modul odgađa
učitavanje dok obavezni servisi nisu dostupni.

Korisne putanje sa zadanom konfiguracijom:

- `/workspaces`: popis vidljivih Područja
- `/workspaces/manage`: kreiranje ili upravljanje Područjem
- `GET /workspaces/acl/subjects`: ograničena serverska pretraga korisnika,
  grupa i ugrađenih publika; zahtijeva pravo upravljanja Područjem
- `GET /workspaces/node/dialog`: ACL-om zaštićen sadržaj modala odabrane stavke
- `POST /workspaces/page/create`: sigurno kreiranje stranice iz otvorenog Područja
- `POST /workspaces/tree/order`: atomsko spremanje vizualnog rasporeda stabla
- `POST /workspaces/workflow`: ACL-om zaštićen prijelaz procesa objave
- `/workspace/{područje}`: početna stranica Područja
- `/workspace/{područje}/{stranica}`: stranica ili link čvor
- `/settings/workspaces`: administratorske postavke
- `/settings/workspaces/all`: administratorski popis
- `/settings/workspaces/deleted`: vraćanje obrisanih Područja

## 10. Razvojne provjere

```bash
composer on-commit
```

Naredba pokreće PHPCS, Rector dry-run, PHPStan za izvorni i testni kod te
PHPUnit. Svaka metoda dokumentirana je na hrvatskom i engleskom. Prikazi
escapeaju ispis, forme koriste framework CSRF polje, a kontroleri prije
pisanja provjeravaju pripadnost Području.
