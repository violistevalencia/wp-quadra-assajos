# Programant Haydn — Quadra d'assajos

Plugin de WordPress, format **Doodle**, per quadrar les agendes dels músics d'un quartet. Cada músic marca en quins dies i franges horàries hi és disponible i el calendari mostra automàticament els dies i hores amb més coincidència.

- **Títol**: «Programant Haydn: Les set últimes paraules de Crist a la creu»
- **Esdeveniment clau i inamovible**: 🎻 Concert de Setmana Santa — **dissabte, 27 de febrer de 2027, a les 20:00 h**
- **Lloc**: Església Santa Magdalena (Vinaròs)
- **Mesos de treball**: novembre 2026, desembre 2026, gener 2027 i febrer 2027

## Instal·lació

1. Comprimeix la carpeta del plugin en un ZIP (per exemple, `quartet-scheduler.zip`).
2. Al panell de WordPress: **Extensions → Afegeix nova → Puja extensió**, tria el ZIP i activa'l.
3. Obre el menú **Quadra assajos** (barra lateral de l'administració) per configurar-ho tot.

## Ús

### 1. Crea la pàgina del calendari
Afegeix el shortcode a la pàgina que vulguis (en un bloc «Shortcode» o per codi):

```
[quadra_assajos]
```

També funciona l'àlies `[quartet_scheduler]`.

### 2. Els músics omplen la seva agenda
A la pàgina amb el shortcode, cada músic:

1. Escriu el seu **nom i cognom** i l'**instrument** als camps del formulari (si el nom ja existeix, s'actualitza la seva disponibilitat existent).
2. Selecciona el mes (pestanyes: novembre, desembre, gener, febrer) i la **setmana** amb les fletxes ‹ ›.
3. Per a cada **franja horària** de cada dia (dilluns–diumenge), tria a la llista desplegable:
   - **Sí** — puc (verd)
   - **Potser** (groc)
   - **No** puc (gris)
   - **—** — sense respondre
4. Prem **Desar la meva disponibilitat**. Es desen tots els dies de la setmana visible; desa cada setmana abans de navegar a una altra.

El dia del concert (27 de febrer) no és seleccionable: hi ha el concert, és inamovible.

### 3. Consulta les coincidències
Sobre la graella del formulari tens:

- **Calendari mensual amb mapa de calor**: com més verd, més músics coincideixen aquell dia, amb una etiqueta `sí/total`. El dia del concert es resalta amb colors morats i grocs i l'etiqueta «CONCERT 20:00 h».
- **Detall d'un dia**: clica el número de qualsevol dia del calendari per veure cada franja horària amb una barra de coincidència (sí / potser), amb enllaç per tancar.
- **Resum en text** a sota del calendari:
  - 🎯 Dies on **coincideixen tots** (el quartet sencer) amb la millor franja horària.
  - 📊 **Millors dies** classificats per nombre de músics disponibles, amb la millor franja de cadascun.

### Sense JavaScript
Pestanyes, fletxes de setmanes, detall de dia i el botó de desar funcionen amb enllaços i un formulari POST normal (patró PRG): tot funciona encara que el navegador o una extensió bloquegi JavaScript. Si JavaScript està actiu, les llistes es tenyeixen de color en triar, com a extra.

## Configuració (administració)

Al menú **Quadra assajos** pots ajustar:

- **Títol, sota-títol i lloc** del cartell.
- **Mesos** de treball (format `AAAA-MM`, per defecte `2026-11, 2026-12, 2027-01, 2027-02`).
- **Data i hora del concert** inamovible (per defecte `2027-02-27 20:00`).
- **Rang d'hores** disponibles (per defecte 10–22 h) i **durada de les franges** (30, 60, 90 o 120 minuts).
- **Llista de músics** amb quants dies han respost i botó per eliminar-los.
- **Esborrar totes les dades** (músics i disponibilitats) amb confirmació.

## Seguretat i tècnica

- El formulari públic porta **nonce** i totes les entrades es netegen i validen (dates `AAAA-MM-DD`, hores `HH:MM`, estats del 0 al 2); el desar fa servir el patró POST–redirigeix–GET.
- Les dades es guarden a la taula `wp_options` (`qs_settings`, `qs_musicians`): no es necessiten taules noves ni permisos especials.
- Sense dependències externes: JS opcional i mínim, CSS propi ajustable al mòbil.
- Compatibilitat: WordPress 5.8 o superior, PHP 7.4+.

## Estructura del codi

```
quartet-scheduler/
├── quartet-scheduler.php         # Plugin: shortcode, desat públic (POST), pàgina d'administració
├── includes/
│   ├── class-qs-store.php        # Configuració i persistència de músics
│   └── class-qs-render.php       # Calendari, formulari, detall diari i resum
└── assets/
    ├── css/qs.css                # Estils (mapa de calor, concert destacat, mòbil)
    └── js/qs.js                  # Mèrica capa estètica (colors de les llistes)
```

## Llicència

GPL-2.0-or-later.
