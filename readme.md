## cmm_jatek_nyomtassteis bővítmény
Ez a bővítmény speciálisan a jatek.nyomtassteis.hu oldalhoz készült. Feladata a települések család számának, felszabadított számnak és rabságban lévő számnak a kijelzése, tovább a nagy összegű megjegyzések audió-vizuális "megköszönése", a település teljes felszabadításának audió-vizuális kijelzése.
A lezárt megrendeléseknél a szállítási cím határozza meg, hogy melyik településhez kerül beszámításra. A kosárban lévő tételeknél az user által utoljára megjelenített "kiemelt település" oldal határozza meg hová számítjuk be. A szállítási cím képernyőn történő megadáshoz a program alapértelmezésként felhozza a user által utoljára megjelenített "kiemelt település" oldal "title" értékét. 
## Licensz: GNU/GPL
## A termékekhez customer field mezőket kell felvenni és az értékeiket megadni
- "package" numeric default 1 (csomag példányszám) 
- "valid_start" datepicker érvényesség kezdő dátuma formátum:"Y.m.d" 
- "valid_end" datepicker érvényesség vég dátuma formátum:"Y.m.d" 

## A kiemelt településhez "cmm_place" egyedi post tipust kell létrehozni, ebbe customer field mezőket kell felvenni:
- "cmm_contineent" select kontinens  * 
- "cmm_country" select ország * 
- "cmm_state" select megye * 
- "cmm_pol_regio1" string országgyülési választó kerület
- "cmm_pol_regio2" string önkormányzati választó kerület </li>
- "cmm_count"    numeric családok száma    
- "cmm_add"      numeric felszabadított család szám kezdő értéke

A * -al jeleztt adatokat a woocommerce kódrendszerével összhangban kell beállítani. 

lásd WC()-countries->get_allowed_countries() és WC()-countries->get_allowed_country_states()[countryCode]

## További követelmények
A különszámok, illetve a saját célra történő megrendeléseket jelentő termékeknél a „valid_start” és a „valid_end” legyen üres, vagy egy már elmúlt időszakot tartalmazzon (pl. 1900.01.01 - 1900.01.01)
Az is megengedett, hogy a különszámoknál a megjelenést követő néhány napos érvényességet állít be az admin. Ez esetben a megadott néhány napban a "felszabadított" számba a különszám előfizetések is be lesznek számolva.
Termék változatokat ne használjunk.
A "virtuális termék", "letölthető termék" NE legyen beállítva.
A kiemelt településekhez tartozó oldalakban a "title" adat a település neve legyen.
A woocommerce -et-t úgy kell konfigurálni, illetve a kiemelt oldalakat kialakítani, hogy amikor a kiemelt település oldalon a "kosárba teszem" -re kattint akkor ezután a kosár lista jelenjen meg!
Ha az oldalon UMS ország térkép is van, akkor azon helyezzünk el markereket 
a kiemelt településekre, ezekben a "title" a település neve legyen 
(azonos írásmódban mint a "kiemelt település" -nél). Ilyenkor a program a megrendelés lezárásakor módosítja a 
szállítási cím településnek megfelelő UMS markert is,

## Short kódok a kiemelt település oldalakra
FONTOS! a kiemelt település oldalakon kötelezően szerepelniük kell ezeknek a short kódoknak! 
### [cmm_init count=#### add=### date="###.##.##"]
FONTOS ez legyen az első behívott short kód az oldalon! mindegyik paraméter elhagyható. 
A "count" az adott településen lévő "családok száma", 
A "count" számot (családok száma) írja ki az outputra. Az "add" és „date” jelentését lásd lentebb! 
Ha "count" és "add" paraméter nincs megadva akkor a "product" ACF mezőkből olvassa ki ezeket az adatokat.

### [cmm_free]
A program a kosárba rakott tételek és a lezárt megrendelések alapján számítja ki a felszabadított családok számát. Csak azokat a termékeket figyelembe véve amik az aktuális dátumon (vagy az [ini...]-ben megadotton) érvényesek 

(valid_start <= vizsgált_Dátum és valid_end >= vizsgált_Dátum) 

Azt nem figyeli, hogy a megrendelés fizetve van vagy nem.
Ha az "add" bemenő paraméter megvan adva akkor a program a kosár és lezárt rendelések alapján számított értékhez hozzáadja az "add" paraméterben megadottat. Ha a "date" meg van adva akkor erre a dátumra végzi a kimutatást, ha nincs megadva akkor az aktuális dátumra. A lezárt megrendeléseknél a szállítási cím határozza meg, hogy melyik településhez kerül beszámításra. A kosárban lévő tételeknél az user által utoljára megjelenített "kiemelt település" oldal határozza meg hová számítjuk be. (A szállítási cím képernyőn történő megadáshoz a program alapértelmezésként felhozza a user által utoljára megjelenített "kiemelt település" oldal "title" értékét.)

Ha az így számított eredmény < 0 akkor nullát ad vissza.

Ha az így számított eredmény > családokSzáma akkor a családokSzáma -t adja vissza.

### [cmm_prison]
a program a "count - free" számítás eredményét adja vissza. az eredmény 
mindig >= 0 és <= családokSzáma

## Short kódok a kosár lista oldalra
FONTOS! a kosár lista oldalon kötelezően szerepelniük kell ezeknek a short kódoknak! 
### [cmm_thanks min=#### img="xxxxxxxxx" audio="xxxxx"]
Az "audio" elhagyható. Az "img" és "audio" URL cím. Akkor jeleníti meg az "img" -ben megadott url-en lévő képet és az "audio" hangot akkor játssza le ha a user kosarába lévő összes érték nagyobb a megadott "min" -nél (minden a user kosarában lévő terméket minden további válogatás nélkül összead)
### [cmm_victory img="xxxxxxxxx" audio="xxxxxxx"]
Az "audio" elhagyható. Az "img" és "audio" URL cím. Akkor jeleníti meg az "img" -ben megadott url-en lévő képet és az "audio" paraméterben lévő hangot akkor játssza le ha az utoljára megjelenített "kiemelt település oldal" "title" település; ennek a kosár tartalomnak a megrendelésével felszabadul (prison = 0)

## Telepítés
    • Wordpress plugin telepítővel a cmm_jatek_nyomtassteis.zip fájlt használva. 
    • Plugin kezelőben bekapcsolás.
## Frissítés
    • Plugin kezelőben kikapcsolás, majd eltávolítás,
    • Újra telepítés a friss zip -fájlt használva,
    • plugin kezelőben bekapcsolás
      
Szerző: Fogler Tibor, Sas Tibor

github.com/utopszkij

tibor.fogler@gmail.com
