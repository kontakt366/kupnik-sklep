<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Slider strzałki — TYLKO desktop (>=992px)
add_action( 'wp_footer', function() { ?>
<script>
(function(){
  // Tylko desktop
  if (window.innerWidth < 992) return;

  document.addEventListener('DOMContentLoaded', function(){
    var nav = document.getElementById('menu-main-menu');
    if (!nav) return;

    // Sprawdź czy menu faktycznie scrolluje (szerokość items > container)
    if (nav.scrollWidth <= nav.clientWidth + 4) return;

    var parent = nav.parentNode;
    var parentPos = window.getComputedStyle(parent).position;
    if (parentPos === 'static') parent.style.position = 'relative';

    function makeBtn(cls, label, html) {
      var b = document.createElement('button');
      b.className = cls;
      b.setAttribute('aria-label', label);
      b.setAttribute('type', 'button');
      b.innerHTML = html;
      return b;
    }

    var btnL = makeBtn('kupnik-nav-prev', 'Poprzednie', '&#8249;');
    var btnR = makeBtn('kupnik-nav-next', 'Następne', '&#8250;');
    parent.appendChild(btnL);
    parent.appendChild(btnR);

    var step = 240;
    btnL.addEventListener('click', function(e){ e.preventDefault(); nav.scrollBy({left:-step, behavior:'smooth'}); });
    btnR.addEventListener('click', function(e){ e.preventDefault(); nav.scrollBy({left:step, behavior:'smooth'}); });

    function upd(){
      btnL.style.opacity = nav.scrollLeft > 4 ? '1' : '0.3';
      btnR.style.opacity = (nav.scrollLeft < nav.scrollWidth - nav.clientWidth - 4) ? '1' : '0.3';
    }
    nav.addEventListener('scroll', upd, {passive:true});
    upd();
  });

  window.addEventListener('resize', function(){
    if (window.innerWidth < 992) {
      document.querySelectorAll('.kupnik-nav-prev,.kupnik-nav-next').forEach(function(b){ b.style.display='none'; });
    }
  });
})();
</script>
<?php });
