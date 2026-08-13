# Backup i povrat područja

Workspace je vlasnik tri providera: potpunih site/component tablica, datoteka privatnih tema područja i selektivnog providera `workspace-scope`. Selektivna arhiva sadrži zapis područja, stablo, ACL čvorova i workflow stanje, postavke naslovnice/prikaza, privatnu temu te prijenosne veze potrebne integracijama editora, kalendara, zadataka, komentara, menija i pretrage.

## Ovlasti

Upravitelj područja smije izvesti područje i vratiti ga u područje za koje ima pravo `manage`. Samo administrator aplikacije smije importati arhivu kao novo područje, vraćati site/component scope ili koristiti destruktivnu obradu konflikata. Serverske provjere rade neovisno o vidljivosti gumba.

## Obrada konflikata

- `merge` ažurira podudarne prijenosne identitete i čuva nepovezane podatke cilja;
- `copy` stvara novi identitet/slug područja i prepisuje scope veze;
- `replace` je samo za administratora i prije promjene stvara sigurnosni snapshot.

Dijeljeni kalendari povezuju se i ponovno koriste ako već postoje; dokumente, verzije, ACL, komentare, zadatke, scoped menije i datoteke privatne teme prenose njihovi vlasnički provideri. Indeks pretrage nikada se ne kopira nego se ponovno izgrađuje nakon uspješne transakcije.

Prije povrata koristite preflight. Nedostajući obvezni provider, pogrešan checksum, nepoznata verzija modula/sheme, ACL problem ili nerazriješen identitet prekidaju radnju prije promjene sadržaja cilja.
