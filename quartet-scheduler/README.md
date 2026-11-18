# Programant Haydn — Quadra d'assajos

Plugin de WordPress, format **Doodle**, per quadrar les agendes dels músics d'un quartet. Cada músic marca en quines dies i franges horàries hi és disponible i el calendari mostra automàticament els dies i hores amb més coincidència.

- **Títol**: «Programant Haydn: Les set últimes paraules de Crist a la creu»
- **Esdeveniment clau i inamovible**: 🎻 Concert de Setmana Santa — **divendres, 27 de febrer de 2026, a les 20:00 h**
- **Lloc**: Església Santa Magdalena (Vinaròs)
- **Mesos de treball**: novembre, desembre, gener i febrer

## Instal·lació

1. Comprimeix la carpeta del plugin en un ZIP (per exemple, `quartet-scheduler.zip`).
2. Al panell de WordPress: **Extensions → Afegeix nova → Puja extensió**, tria el ZIP i activa'l.
3. Anomena el menú **Quadra assajos** (barra lateral de l'administració) per configurar tot.

## Ús

### 1. Crea la pàgina del calendari
Afegeix el shortcode a la pàgina que vulguis (en un bloc «Shortcode» o per codi):

```
[quadra_assajos]
```

També funciona l'àlies `[quartet_scheduler]`.

### 2. Els músics omplen la seva agenda
 A la pàgina amb el shortcode, cada músic:

1. Tria el seu nom a la llista (si ja s'havia d'inscrit) o selecciona l'opció **Afegeix-me i torna-ho a triar** i escriu **nom i instrument**.
2. Selecciona el mes (pestanyes: novembre, desembre, gener, febrer) i la **setmana** amb les fletxes ‹ ›.
3. Per a cada **franja horària** de cada dia (dilluns–diumenge), fa clic per alternar l'estat:
   - Verd — **Sí, puc**
   - Groc — **Potser**
   - Gris — **No puc**
   - Blanc — sense respondre
4. Prem **Desar la meva disponibilitat**.

El dia del concert (27 de febrer) no és seleccionable: hi ha el concert, és inamovible.

### 3. Consulta les coincidències
Sobre la graella del formulari tens:

- **Calendari mensual amb mapa de calor**: com més verd, més músics coincideixen aquell dia i una etiqueta amb `sí/total`. El dia del concert es resalta amb colors morats i grocs, amb l'advocació «CONCERT 20:00 h».
- **Detall d'un dia**: clica qualsevol dia del calendari per veure cada franja horària amb una barra de coincidència (sí / potser).
- **Resum en text** a sota del calendari:
  - 🎯 Dies on **coincideixen tots** (el quartet sencer) amb la millor franja horària.
  - 📊 **Millors dies** classificats per nombre de músics disponibles, amb la millor franja de cadascun.

## Configuració (administració)

Al menú **Quadra assajos** pots ajustar:

- **Títol, sota-títol i lloc** del cartell.
- **Mesos** de treball (format `AAAA-MM`, per defecte `2025-11, 2025-12, 2026-01, 2026-02`).
- **Data i hora del concert** inamovible (per defecte `2026-02-27 20:00`).
- **Rang d'hores** disponibles (per defecte 10–22 h) i **durada de les franges** (30, 60, 90 o 120 minuts).
- **Llista de músics** amb la quantitat de dies respondre i botó per eliminar-los.
- **Esborrar totes les dades** (músics i disponibilitats) amb confirmació.

## Seguretat i tècnica

- Els endpoints AJAX porten **nonce** i totes les entrades són net i validades (dates `AAAA-MM-DD`, hores `HH:MM`, estats del 0 al 2).
- Les dades es guarden a la taula `wp_options` (`qs_settings`, `qs_musicians`): no es necessiten taules noves ni permisos especials.
- Sense dependències externes: només PHP estàndard + jQuery no requerit (JS autònom), CSS pròpi ajustable al mòbil.
- Compatibilitat: WordPress 5.8 o superior, PHP 7.4+.

## Estructura del codi

```
quartet-scheduler/
├── quartet-scheduler.php         # Plugin: hooks, shortcode, AJAX, pàgina d'administració
├── includes/
│   ├── class-qs-store.php        # Configuració i persistència de músics
│   └── class-qs-render.php       # Calendari, formulari, detall diari i resum
└── assets/
    ├── css/qs.css                # Estils (mapa de calor, concert destacat, mòbil)
    └── js/qs.js                  # Formulari Doodle, navegació de setmanes i desat AJAX
```

## Licència

GPL-2.0-or-later.
