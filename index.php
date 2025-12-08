<?php
// ==== CONFIGURATION ====
$baseDir = __DIR__;
$folders = [];
$svgMap = [];

// Recursively find folders containing SVG files
function scanFolders($dir, &$folders, &$svgMap) {
    $hasSvg = false;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = "$dir/$f";
        if (is_dir($path)) {
            $childHasSvg = scanFolders($path, $folders, $svgMap);
            $hasSvg = $hasSvg || $childHasSvg;
        } elseif (str_ends_with(strtolower($f), '.svg')) {
            $svgMap[$dir][] = "$dir/$f";
            $hasSvg = true;
        }
    }
    if ($hasSvg) $folders[] = $dir;
    return $hasSvg;
}
scanFolders($baseDir, $folders, $svgMap);

// Default folder = first one or selected one
$currentFolder = isset($_GET['folder']) && isset($svgMap[$_GET['folder']])
    ? $_GET['folder']
    : ($folders[0] ?? $baseDir);

$svgFiles = $svgMap[$currentFolder] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SVG Icons Viewer</title>
<style>
:root {
  --primary: rgb(108, 92, 230);
  --bg: #f8f8f8;
}
body {
  font-family: system-ui, sans-serif;
  background: var(--bg);
  color: #222;
  margin: 0;
  padding: 0;
}
header {
  background: var(--primary);
  color: white;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .8rem;
}
.topbar {
  display: flex;
  gap: .6rem;
  flex-wrap: wrap;
}
header input[type="search"] {
  flex: 1;
  padding: .6rem .8rem;
  border-radius: 6px;
  border: none;
  font-size: 1rem;
}
header select {
  padding: .6rem .8rem;
  border-radius: 6px;
  border: none;
  font-size: 1rem;
}
.controls {
  display: grid;
  grid-template-columns: repeat(auto-fit,minmax(160px,1fr));
  gap: .6rem;
}
.controls label {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: rgba(255,255,255,.2);
  border-radius: 6px;
  padding: .4rem .6rem;
}
.controls input[type="color"],
.controls input[type="text"],
.controls input[type="number"],
.controls input[type="range"] {
  flex: 1;
  min-width: 0;
  border: none;
  border-radius: 4px;
  padding: .3rem;
  font-size: .9rem;
}
main {
  padding: 1rem;
  display: grid;
  grid-template-columns: repeat(auto-fill,minmax(80px,1fr));
  gap: 1rem;
  transition: background .3s;
}
.icon {
  text-align: center;
  cursor: pointer;
  user-select: none;
}
.icon svg {
  width:48px;
  height: 48px;
  fill: var(--color,#000);
  transition: transform .15s;
}
.icon:hover svg {
  transform: scale(1.1);
}
#notify {
  position: fixed;
  top: -60px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--primary);
  color: white;
  padding: .6rem 1rem;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,.3);
  transition: top .4s;
  z-index: 100;
}
#notify.show { top: 10px; }
</style>
</head>
<body>

<header>
  <div class="topbar">
    <input type="search" id="search" placeholder="Search icons...">
    <form method="get">
      <select style="max-width: 200px;" name="folder" onchange="this.form.submit()">
        <?php foreach ($folders as $folder):
          $rel = str_replace($baseDir.'/', '', $folder);
          $sel = $folder === $currentFolder ? 'selected' : '';
          echo "<option value='".htmlspecialchars($folder,ENT_QUOTES)."' $sel>$rel</option>";
        endforeach; ?>
      </select>
    </form>
  </div>
  <div class="controls">
    <label>Size <input type="range" id="sizeRange" min="16" max="200" value="20"><span id="sizeVal">20px</span></label>
    <label>Manual px <input type="number" id="sizeInput" value="20"></label>
    <label>Color <input type="color" id="colorPicker" value="#000000"></label>
    <label>Hex/RGB <input type="text" id="colorText" placeholder="#000000 or rgb(0,0,0)"></label>
     <label for="apply_settings">Apply Settings <input id="apply_settings" type="checkbox" checked></label>
  </div>
</header>

<main id="grid">
<?php 
if (empty($svgFiles)) {
  echo "<p style='grid-column:1/-1;text-align:center;opacity:.6;'>No SVG files found in this folder.</p>";
}
foreach ($svgFiles as $svg):
  $name = basename($svg, '.svg');
  $content = file_get_contents($svg);
  echo "<div class='icon' data-name='".htmlspecialchars(strtolower($name),ENT_QUOTES)."'>".$content."</div>";
endforeach; 
?>
</main>

<div id="notify">Copied!</div>

<script>
const grid = document.getElementById('grid');
const search = document.getElementById('search');
const sizeRange = document.getElementById('sizeRange');
const sizeInput = document.getElementById('sizeInput');
const colorPicker = document.getElementById('colorPicker');
const colorText = document.getElementById('colorText');
const notify = document.getElementById('notify');
const sizeVal = document.getElementById('sizeVal');

let currentColor = '#000000';
let currentSize = 20;

function applySettings(){
  document.documentElement.style.setProperty('--color', currentColor);
  document.documentElement.style.setProperty('--size', currentSize + 'px');
  sizeVal.textContent = currentSize + 'px';
  adjustBgContrast(currentColor);
}

function adjustBgContrast(color){
  const c = document.createElement('canvas');
  const ctx = c.getContext('2d');
  ctx.fillStyle = color;
  const rgb = ctx.fillStyle.match(/\d+/g)?.map(Number);
  if(!rgb) return;
  const brightness = (rgb[0]*299 + rgb[1]*587 + rgb[2]*114) / 1000;
  document.body.style.background = brightness > 180 ? '#000' : '#f8f8f8';
  document.body.style.color = brightness > 180 ? '#fff' : '#222';
}

search.addEventListener('input',()=>{
  const q = search.value.toLowerCase();
  document.querySelectorAll('.icon').forEach(icon=>{
    icon.style.display = icon.dataset.name.includes(q)?'block':'none';
  });
});

sizeRange.addEventListener('input',e=>{
  currentSize = +e.target.value;
  sizeInput.value = currentSize;
//  applySettings();
});

sizeInput.addEventListener('input',e=>{
  let v = parseInt(e.target.value);
  if(!isNaN(v) && v>0){ 
    currentSize = v;
     sizeRange.value=v;
   //  applySettings();
     }
});

function validColor(str){
  const s = new Option().style;
  s.color = str;
  return s.color !== '';
}

colorPicker.addEventListener('input',e=>{
  currentColor = e.target.value;
  colorText.value = e.target.value;
  applySettings();
});
colorText.addEventListener('change',e=>{
  const v = e.target.value.trim();
  if(validColor(v)){
    currentColor = v;
    colorPicker.value = v.startsWith('#') ? v : '#000000';
    applySettings();
  } else {
    e.target.style.border = '2px solid red';
    setTimeout(()=>e.target.style.border='',1500);
  }
});

document.querySelectorAll('.icon').forEach(icon=>{
  icon.addEventListener('click',()=>{
  let div=document.createElement('div');
  div.innerHTML=icon.innerHTML;
  if(!div.querySelector('svg').getAttribute('height')){
    div.querySelector('svg').setAttribute('height','20');
    div.querySelector('svg').setAttribute('width','20');
  }
  let svg_icon=div.innerHTML;
   if(document.querySelector('#apply_settings').checked == false){
     navigator.clipboard.writeText(svg_icon).then(()=>{
      notify.classList.add('show');
      setTimeout(()=>notify.classList.remove('show'),1500);
    });
     return;
   }
   
   
    currentColor='CurrentColor';
    const svg = svg_icon
      .replace(/fill="[^"]*"/g, `fill="${currentColor}"`)
      .replace(/width="[^"]*"/g, `width="${currentSize}"`)
      .replace(/height="[^"]*"/g, `height="${currentSize}"`);
    navigator.clipboard.writeText(svg).then(()=>{
      notify.classList.add('show');
      setTimeout(()=>notify.classList.remove('show'),1500);
    });
  });
});

applySettings();
</script>
</body>
</html>