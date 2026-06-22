<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

/**
 * Slider menu — show/hide 5 items na raz (bez overflow, kondmienu działa normalnie)
 * Tylko desktop >= 992px
 */
add_action( 'wp_footer', function() { ?>
<script>
(function(){
  if (window.innerWidth < 992) return;

  document.addEventListener('DOMContentLoaded', function(){
    var ul = document.getElementById('menu-main-menu');
    if (!ul) return;

    var items = Array.from(ul.children).filter(function(li){
      return li.classList.contains('menu-item');
    });

    if (items.length <= 5) return; // nie trzeba slidera

    var PER_PAGE = 5;
    var current  = 0;

    // Opakuj ul w wrapper ze strzałkami
    var wrap = document.createElement('div');
    wrap.className = 'kupnik-nav-wrap';
    ul.parentNode.insertBefore(wrap, ul);
    wrap.appendChild(ul);

    var btnL = document.createElement('button');
    var btnR = document.createElement('button');
    btnL.className = 'kupnik-nav-prev';
    btnR.className = 'kupnik-nav-next';
    btnL.type = 'button';
    btnR.type = 'button';
    btnL.setAttribute('aria-label','Poprzednie');
    btnR.setAttribute('aria-label','Następne');
    btnL.innerHTML = '&#8249;';
    btnR.innerHTML = '&#8250;';
    wrap.insertBefore(btnL, ul);
    wrap.appendChild(btnR);

    function show() {
      items.forEach(function(li, i) {
        li.style.display = (i >= current && i < current + PER_PAGE) ? '' : 'none';
      });
      btnL.disabled = current === 0;
      btnR.disabled = current + PER_PAGE >= items.length;
      btnL.style.opacity = btnL.disabled ? '0.3' : '1';
      btnR.style.opacity = btnR.disabled ? '0.3' : '1';
    }

    btnL.addEventListener('click', function(){ if(current>0){ current--; show(); }});
    btnR.addEventListener('click', function(){ if(current+PER_PAGE<items.length){ current++; show(); }});

    show();
  });
})();
</script>
<?php });
