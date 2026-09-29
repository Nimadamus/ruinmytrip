// Builds public/assets/data/world-map.json, the geometry behind /map and the map share card.
//
// Run once, from a scratch folder that has the three packages installed, and commit the output:
//
//   npm i world-atlas@2.0.2 d3-geo@3 topojson-client@3
//   node /path/to/ruinmytrip/scripts/build_world_map.mjs /path/to/that/folder/node_modules
//
// The output is plain polygons already projected onto a 1000 x 520 grid (Natural Earth), so the
// browser can draw them as SVG and PHP can draw the very same rings with GD for the share image:
// one geometry, two renderers, no map library at runtime. Source: Natural Earth 1:50m via
// world-atlas, public domain.
import { createRequire } from 'node:module';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const mods = process.argv[2];
if (!mods) { console.error('usage: node build_world_map.mjs <node_modules>'); process.exit(1); }
const require = createRequire(join(mods, 'x.js'));
const topo = require('world-atlas/countries-50m.json');
const { feature } = require('topojson-client');
const d3 = await import('file://' + join(mods, 'd3-geo', 'src', 'index.js').replace(/\\/g, '/'));

const W = 1000, H = 520;
// Full names where Natural Earth abbreviates, because people search for the name they know.
const NAMES = {
  'Marshall Is.': 'Marshall Islands', 'N. Mariana Is.': 'Northern Mariana Islands',
  'U.S. Virgin Is.': 'U.S. Virgin Islands', 'S. Geo. and the Is.': 'South Georgia',
  'Br. Indian Ocean Ter.': 'British Indian Ocean Territory', 'Pitcairn Is.': 'Pitcairn Islands',
  'Falkland Is.': 'Falkland Islands', 'Cayman Is.': 'Cayman Islands',
  'British Virgin Is.': 'British Virgin Islands', 'Turks and Caicos Is.': 'Turks and Caicos Islands',
  'S. Sudan': 'South Sudan', 'Solomon Is.': 'Solomon Islands',
  'St. Vin. and Gren.': 'Saint Vincent and the Grenadines', 'St. Kitts and Nevis': 'Saint Kitts and Nevis',
  'Cook Is.': 'Cook Islands', 'W. Sahara': 'Western Sahara', 'St. Pierre and Miquelon': 'Saint Pierre and Miquelon',
  'Wallis and Futuna Is.': 'Wallis and Futuna', 'Fr. Polynesia': 'French Polynesia',
  'Fr. S. Antarctic Lands': 'French Southern Lands', 'Eq. Guinea': 'Equatorial Guinea',
  'Dominican Rep.': 'Dominican Republic', 'Faeroe Is.': 'Faroe Islands', 'N. Cyprus': 'Northern Cyprus',
  'Dem. Rep. Congo': 'DR Congo', 'Central African Rep.': 'Central African Republic',
  'Bosnia and Herz.': 'Bosnia and Herzegovina', 'Indian Ocean Ter.': 'Australian Indian Ocean Territories',
  'Heard I. and McDonald Is.': 'Heard and McDonald Islands', 'Ashmore and Cartier Is.': 'Ashmore and Cartier Islands',
  'Antigua and Barb.': 'Antigua and Barbuda', 'Czechia': 'Czech Republic', 'eSwatini': 'Eswatini',
  "Côte d'Ivoire": "Côte d'Ivoire",
};
// Four shapes carry no ISO number. Kosovo is a destination people go to and gets a key of its
// own; the other three are drawn as land and cannot be picked.
const EXTRA = { 'Kosovo': 'xk' };

const fc = feature(topo, topo.objects.countries);
const proj = d3.geoNaturalEarth1().fitExtent([[4, 4], [W - 4, H - 4]], { type: 'Sphere' });
const path = d3.geoPath(proj).digits(1);
const out = [];
for (const f of fc.features) {
  const raw = f.properties.name;
  const id = f.id ?? EXTRA[raw] ?? null;
  // Let d3 project AND clip (Russia and Fiji cross the antimeridian; projecting points one by one
  // would draw a stripe across the whole map), then read the path back as rings of whole-number
  // points. Rounding to 1px on a 1000px map is invisible and halves the file.
  const rings = [];
  for (const seg of (path(f) || '').split('M').slice(1)) {
    const nums = seg.replace(/Z/g, '').split(/[L,]/).map(Number);
    const pts = [];
    let lx = null, ly = null;
    for (let i = 0; i + 1 < nums.length; i += 2) {
      const x = Math.round(nums[i]), y = Math.round(nums[i + 1]);
      if (x === lx && y === ly) continue;
      pts.push(x, y); lx = x; ly = y;
    }
    if (pts.length >= 6) rings.push(pts);
  }
  const b = path.bounds(f);
  const tiny = (b[1][0] - b[0][0]) * (b[1][1] - b[0][1]) < 30;
  const [cx, cy] = path.centroid(f).map(Math.round);
  out.push({ id: id === null ? null : String(id), name: NAMES[raw] ?? raw, rings, tiny, c: [cx, cy] });
}
out.sort((a, b) => a.name.localeCompare(b.name));
const dest = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'assets', 'data', 'world-map.json');
writeFileSync(dest, JSON.stringify({ w: W, h: H, source: 'Natural Earth 1:50m via world-atlas 2.0.2', countries: out }));
console.log('countries', out.length, 'pickable', out.filter(c => c.id).length, '->', dest);
