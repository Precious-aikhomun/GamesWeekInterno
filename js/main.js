(function(){
  var el=document.getElementById('cd');var t=Date.parse((el&&el.dataset.target)||'2027-01-18T12:00:00+01:00');
  function p(n){return String(n).padStart(2,'0')}
  function tick(){
    var s=Math.max(0,Math.floor((t-Date.now())/1000));
    document.getElementById('dd').textContent=p(Math.floor(s/86400));
    document.getElementById('hh').textContent=p(Math.floor(s%86400/3600));
    document.getElementById('mm').textContent=p(Math.floor(s%3600/60));
    document.getElementById('ss').textContent=p(s%60);
  }
  tick();setInterval(tick,1000);
})();

(function(){
  var b=document.getElementById('burger'),m=document.getElementById('menu');
  function set(o){m.classList.toggle('open',o);b.setAttribute('aria-expanded',o);b.setAttribute('aria-label',o?'Cerrar menú':'Abrir menú')}
  b.addEventListener('click',function(){set(!m.classList.contains('open'))});
  m.addEventListener('click',function(e){if(e.target.tagName==='A')set(false)});
  window.addEventListener('resize',function(){if(innerWidth>860)set(false)});
})();
