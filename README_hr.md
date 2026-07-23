# HeartPhrame Workspace modul

Workspace modul organizira povezani sadržaj u **Područja** (`Workspaces` na
engleskom). Svako Područje ima svoju putanju, vlasnika, vidljivost, članove,
prava i hijerarhijsko stablo stranica.

English documentation: [README.md](README.md)

## Mogućnosti

- ugrađene publike **Javno** i **Svi prijavljeni** uz ograničena Područja
- prava korisnika i grupa: pregled, dodavanje, uređivanje, brisanje i upravljanje
- asinkrono pretraživanje Auth imenika bez ispisivanja svih korisnika i grupa
- ograničenja po stranici koja nasljeđuju svi potomci
- hijerarhijski čvorovi za dokumente, interne i vanjske linkove
- sakrivo i responzivno stablo stranica
- kreiranje nove stranice izravno iz otvorenog Područja
- soft delete Područja i administratorsko vraćanje
- opcionalna integracija s HTML editorom za sadržaj, verzije i privitke
- proces objave po stranici i jeziku: nacrt, pregled, objavljeno i arhivirano
- čitatelji i dalje vide zadnju objavljenu nepromjenjivu verziju dok se uređuje nacrt
- opcionalne in-app i e-mail obavijesti za pregled i objavu
- opcionalna Menu integracija za glavni izbornik i Postavke
- prijenosna inicijalna shema za SQLite, PostgreSQL i MySQL/MariaDB

Ograničenja stranice mogu samo suziti prava dodijeljena na Području. Ne mogu
dati pristup korisniku ili grupi koji već nemaju prava na Području. Vlasnik
Područja i administratori aplikacije zadržavaju pravo upravljanja. U
arhiviranom Području i njima su isključeni dodavanje, uređivanje i brisanje
sadržaja dok ga ponovno ne aktiviraju.

`Javno` je ugrađena publika samo za čitanje. `Svi prijavljeni` također nije
stvarna Auth grupa, ali može dobiti šira prava. Obrazac prikazuje samo
dodijeljene ACL retke; korisnici i grupe dodaju se ograničenom serverskom
pretragom koja ne učitava cijeli imenik.

## Preduvjeti

- PHP 8.2 ili noviji
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

HTML editor, Menu, Notification i E-mail modul su opcionalne integracije.

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
  stranica i stranica poslanih na pregled prema pravima trenutnog korisnika;
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
