# HeartPhrame Workspace modul

[English version](README.md)

Workspace modul organizira povezani sadržaj u **Područja** (`Workspaces` na
engleskom). Svako Područje ima svoju putanju, vlasnika, vidljivost, članove,
prava i hijerarhijsko stablo stranica.

## Ovisnosti

Obavezno, redoslijedom uključivanja:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-workspace` (`dev-main`)

Opcionalne integracije:

- HTML Editor daje stranice i uređivanje, a Menu dodaje navigaciju.
- Notification obavještava recenzente/autore; E-mail može slati kopije.
- API dodaje ACL Workspace resurse i rute za upravljanje stablom.

```bash
composer require aaieduhr/heartphrame-module-workspace:dev-main
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

English documentation: [README.md](README.md)

## Mogućnosti

- ugrađene publike **Javno** i **Svi prijavljeni** uz ograničena Područja
- prava korisnika i grupa: pregled, dodavanje, uređivanje, objavljivanje, brisanje i upravljanje
- asinkrono pretraživanje Auth imenika bez ispisivanja svih korisnika i grupa
- ograničenja po stranici koja nasljeđuju svi potomci
- ACL-filtrirani Sažetci s renderiranim isječcima, razinama, brojem i redoslijedom članaka
- hijerarhijski čvorovi za dokumente, interne i vanjske linkove
- sakrivo i responzivno stablo stranica
- kreiranje nove stranice izravno iz otvorenog Područja
- soft delete Područja i administratorsko vraćanje
- opcionalna integracija s HTML editorom za sadržaj, verzije i privitke
- proces objave po stranici i jeziku: nacrt, pregled, objavljeno i arhivirano
- čitatelji i dalje vide zadnju objavljenu nepromjenjivu verziju dok se uređuje nacrt
- opcionalne in-app i e-mail obavijesti za pregled i objavu
- opcionalna Menu integracija za glavni izbornik i Postavke
- javni, prijavljeni i osobni odabir naslovnice aplikacije uz siguran ACL fallback
- opcionalni verzionirani REST API za podatke područja, ACL i linkove u stablu
- prijenosna inicijalna shema za SQLite, PostgreSQL i MySQL/MariaDB

Ograničenja stranice mogu samo suziti prava dodijeljena na Području. Ne mogu
dati pristup korisniku ili grupi koji već nemaju prava na Području. Vlasnik
Područja i administratori aplikacije zadržavaju pravo upravljanja. U
arhiviranom Području i njima su isključeni dodavanje, uređivanje i brisanje
sadržaja dok ga ponovno ne aktiviraju.

Za pregled ograničenja uključite **Uredi stablo** i odaberite olovku uz
stranicu. Zeleni checkbox prikazuje pravo naslijeđeno iz Područja, a crveni
pravo zadržano izravnim ograničenjem te stranice. Spremanje bez ijedne crvene
oznake uklanja izravno ograničenje i vraća potpuno nasljeđivanje.

`Javno` je ugrađena publika samo za čitanje. `Svi prijavljeni` također nije
stvarna Auth grupa, ali može dobiti šira prava. Obrazac prikazuje samo
dodijeljene ACL retke; korisnici i grupe dodaju se ograničenom serverskom
pretragom koja ne učitava cijeli imenik.

## Preduvjeti

- PHP 8.2 ili noviji
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

HTML editor, API, Menu, Notification i E-mail modul su opcionalne integracije.

## API integracija

Workspace objavljuje neutralne opise scopeova `workspace:read` i
`workspace:manage` iz `config/api.php` bez ovisnosti o API modulu. Kada je
instaliran i API modul, uvjetno se izlažu verzionirane Workspace rute ispod
`/api/v1/workspaces`.

`workspace:manage` obuhvaća podatke područja, soft brisanje i vraćanje, ACL,
redoslijed stabla te interne i vanjske link-čvorove. Ne kreira niti briše HTML
dokumente i privitke; oni ostaju odgovornost HTML editora. Svaka operacija
provjerava i scope ključa i efektivni Workspace ACL njegova vlasnika. Široki
scope nikada ne pretvara neovlaštenog korisnika u upravitelja.

Popis ruta i ponašanje odgovora nalaze se u
[docs/index_hr.md](docs/index_hr.md#10-api-integracija).

## Instalacija

```bash
composer require aaieduhr/heartphrame-module-workspace
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

Paket treba dodati nakon Auth i ORM modula u `app.modules.enabled`:

```php
'aaieduhr/heartphrame-module-workspace',
```

Kopirajte `config/workspace.php` u host aplikaciju ako želite promijeniti
zadane vrijednosti.

Migracija ne kreira probno Područje, korisnike, grupe ni stranice.

U aplikaciji koja je već pokrenula stariju Workspace migraciju jednom instalirajte
dodatnu migraciju naslovnice:

```bash
vendor/bin/hph workspace:install-homepage-migration
vendor/bin/hph orm-migrate:up
```

Ako je ta migracija naslovnice već bila primijenjena prije uvođenja
strukturiranih ciljeva Sažetaka, dodajte i kompatibilne stupce prikaza:

```bash
vendor/bin/hph workspace:install-homepage-view-options-migration
vendor/bin/hph orm-migrate:up
```

## Sažetci Područja

Svako vidljivo stablo Područja prikazuje ikonu **Sažetci**. Stranica na
`/{korijen-područja}/{područje}/shorts` renderira točno objavljenu Editor
verziju svake dopuštene stranice kao isječak visine dvanaest redaka s fade
završetkom i poveznicom **Pročitaj više**. Tako ostaje mjesta za približno pet
do šest dodatnih redaka teksta i kada članak počinje kompaktnom slikom. Nacrti, arhivirane objave,
nedostupne stranice i svi potomci nedostupne stranice uklanjaju se prije
učitavanja sadržaja.

Posjetitelj bira samo 1., razine 1–2 ili razine 1–3; 5, 10, 25, 50 ili sve
članke; te hijerarhijski redoslijed, najnovije ili najstarije prvo. **Sve** je
dostupno samo kada manje od 100 članaka prođe provjeru objave i ACL-a. Server
isto pravilo provodi i za ručno sastavljen query string.

Zadane vrijednosti postavljaju se pod **Postavke → Područja** i spremaju u
aplikacijski `config/workspace.php`:

```php
'shorts' => [
    'depth' => 2,
    'limit' => 10,
    'order' => 'newest',
    'display_options_visible' => false,
],
```

Stablo je početno otvoreno, a ploča **Opcije prikaza** sklopljena. Njihovi
uvijek dostupni ikon-gumbi prate temu te imaju pristupačne nazive i opise.
Izravna poveznica može promijeniti bilo koje stanje parametrima `tree=0|1` i
`options=0|1`; obrazac filtra čuva trenutačni odabir posjetitelja. Sadržaj prvo
koristi točno objavljenu verziju aktivnog jezika, a zatim zadani jezik sitea iz
`app.localization.locale` u `config/app.php`; nikada ne koristi nacrt kao
jezični fallback.

To su postavke sitea, a ne dizajn teme. Potpuni backup sitea treba uključiti
`config/workspace.php`; izvoz paketa teme ne preuzima i ne treba preuzimati te
vrijednosti.

## Naslovnica aplikacije

Administrator postavlja naslovnicu u **Postavke → Područja → Naslovnica
aplikacije**. Može odabrati objavljenu stranicu ili prikaz **Sažetaka** za
neprijavljene goste, drugi cilj dostupan svim prijavljenim korisnicima i
dopuštenje osobnog izbora u Auth profilu korisnika. Kada je cilj prikaz
Sažetaka, prikazuju se strukturirani prekidači **Vidljivo stablo stranica** i
**Vidljive opcije prikaza**, a ne slobodno tekstualno polje query parametara.

Za prijavljenog korisnika redoslijed je osobna stranica, zadana za prijavljene,
javna zadana te ugrađena naslovnica host aplikacije. Gost koristi javnu pa
ugrađenu naslovnicu. Svaki zahtjev ponovno provjerava aktualni Workspace ACL i
stanje objave; obrisana, neobjavljena ili naknadno ograničena stranica preskače
se umjesto prikaza greške `403` na naslovnici.

Host aplikacija na ruti `/` može koristiti neutralni servis
`heartphrame.application_homepage_resolver` i napraviti privremeni redirect
bez cacheiranja na kanonsku Workspace stranicu. Auth ne ovisi o Workspaceu:
profilnu sekciju registrira isključivo Workspace dok je uključen.

Postavke i osobni izbori spremaju se u tablice
`workspace_homepage_settings` i `workspace_user_homepages`. Potpuni backup
baze/sitea mora obuhvatiti obje tablice. To su sadržajne postavke sitea i
namjerno ne pripadaju izvozu paketa teme. Vrsta strukturiranog cilja, ID
Područja i oba prekidača vidljivosti spremaju se u te tablice pa ih standardni
backup i povrat baze čuvaju bez gubitka ponašanja naslovnice.

## Integracija s HTML editorom

Workspace modul ne sprema HTML. Čvor stabla povezuje sa stabilnim ključem
editorova dokumenta kroz opcionalni servisni most.

Kada su oba modula uključena:

- Workspace putanje i nasljedni ACL upravljaju pristupom povezanom dokumentu;
- samostalna javna slug putanja editora je isključena;
- ovlašteni članovi mogu dodavati, uređivati i brisati povezane stranice;
- obični urednik automatski kreira novi dokument i ne može pogađanjem ključa
  povezati tuđi postojeći dokument; povezivanje postojećih dokumenata dostupno
  je administratoru;
- interne apsolutne putanje razrješavaju se unutar aplikacijskog prefiksa, pa
  `/calendars` radi i kada je aplikacija instalirana pod `/hfc`;
- stranica koristi isti potpuni pregled kao HTML editor: temu, jezike, povijest,
  privitke, ZIP export, sadržaj dokumenta, audit podatke i responzivno ponašanje;
- Workspace dodaje samo lijevo stablo, a efektivni ACL čvora određuje prikaz
  uređivanja, povijesti i ostalih zaštićenih akcija;
- verzije i privitci i dalje pripadaju HTML editoru;
- nova ili promijenjena stranica postaje nacrt, a samo izričita objava mijenja
  nepromjenjivu verziju koju vide čitatelji;
- postoji samo jedan zajednički nacrt po stranici i jeziku; običan pregled uvijek
  pokazuje zadnju objavu, a nacrt se posebno uređuje ili pregledava;
- urednici mogu poslati sadržaj na pregled ili ga vratiti u nacrt, korisnici s
  pravom objavljivanja mogu ga objaviti, a upravitelji arhiviraju i vraćaju stranice;
- slanje na pregled obavještava efektivne objavljivače, a objava korisnika koji
  je nacrt poslao; Notification inbox je primaran, dok E-mail modul može staviti
  opcionalnu SMTP kopiju u red;
- stablo označava nove neobjavljene stranice, a zaglavlje stabla nudi popise novih
  stranica, stranica poslanih na pregled i poveznicu na Sažetke;
- Sažetci nakon filtriranja razina, objave, ACL-a, redoslijeda i količine jednim
  opcionalnim batch pozivom traže točno objavljene Editor verzije;
- jedan editor dokument može pripadati samo jednoj aktivnoj Workspace stranici.

HTML editor nastavlja samostalno raditi kada Workspace modul nije instaliran.
Njegov samostalni pregled uvijek koristi aktualnu verziju editora i ne prikazuje
Workspace kontrole procesa objave.

## Dokumentacija

Detaljna arhitektura i upute razumljive početnicima nalaze se u
[docs/index_hr.md](docs/index_hr.md).

## Licenca

Modul je objavljen pod
[European Union Public License (EUPL) v1.2](LICENSE).

## Politika ovisnosti

Framework i interni HeartPhrame moduli zahtijevaju se s pomične grane
`dev-main`. Ovaj modul ne sprema `composer.lock`; CI dohvaća najnovija
razvojna stanja i pokreće cijeli skup provjera `composer on-commit`.
