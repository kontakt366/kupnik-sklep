<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Tłumaczenia
add_filter( 'gettext', function( $translated, $original ) {
    $map = [
        'My Account' => 'Konto',
        'My account' => 'Konto',
        'Log In'     => 'Zaloguj się',
        'Log Out'    => 'Wyloguj się',
        'Register'   => 'Zarejestruj się',
    ];
    return $map[ $original ] ?? $translated;
}, 20, 2 );

// Slider + wielokolumnowe podmenu — tylko desktop
add_action( 'wp_footer', function() { ?>
<script>
(function(){
  if (window.innerWidth < 992) return;

  // window.load — Porto inicjalizuje mega-menu w DOMContentLoaded, więc czekamy na load
  window.addEventListener('load', function(){
    var ul = document.getElementById('menu-main-menu');
    if (!ul) return;

    /* ---- 1. SLIDER ---- */
    var items = Array.from(ul.children).filter(function(li){
      return li.classList.contains('menu-item');
    });
    var PER = 5, cur = 0;

    if (items.length > PER) {
      var parent = ul.parentNode;
      if (window.getComputedStyle(parent).position === 'static') {
        parent.style.position = 'relative';
      }

      var btnL = document.createElement('button');
      var btnR = document.createElement('button');
      btnL.type = btnR.type = 'button';
      btnL.className = 'kupnik-nav-prev'; btnR.className = 'kupnik-nav-next';
      btnL.innerHTML = '&#8249;'; btnR.innerHTML = '&#8250;';
      parent.insertBefore(btnL, ul);
      parent.appendChild(btnR);

      function show() {
        items.forEach(function(li, i){
          li.style.display = (i >= cur && i < cur + PER) ? '' : 'none';
        });
        btnL.style.opacity = cur === 0 ? '0.3' : '1';
        btnR.style.opacity = (cur + PER >= items.length) ? '0.3' : '1';
      }
      btnL.addEventListener('click', function(){ if(cur>0){cur--;show();} });
      btnR.addEventListener('click', function(){ if(cur+PER<items.length){cur++;show();} });
      show();
    }

    /* ---- 2. WIELOKOLUMNOWE PODMENU ---- */
    // Porto owija dropdown: li > div.popup > div.inner > ul.sub-menu (nie li > ul.sub-menu!)
    var MAX_PER_COL = 7;
    var COL_W = 200;

    Array.from(ul.children).forEach(function(topLi){
      var sub = topLi.querySelector('.popup .inner ul.sub-menu');
      if (!sub || sub.dataset.kupnikCols) return;

      var lis = Array.from(sub.children);
      if (lis.length <= MAX_PER_COL) return;

      sub.dataset.kupnikCols = '1';
      var cols = Math.ceil(lis.length / MAX_PER_COL);
      var totalW = cols * COL_W;

      // Usuń narrow — Porto ogranicza szerokość popup przez tę klasę
      topLi.classList.remove('narrow');

      var popup = topLi.querySelector('.popup');
      var inner = topLi.querySelector('.popup .inner');
      if (popup) popup.style.setProperty('min-width', totalW + 'px', 'important');
      if (inner) { inner.style.width = totalW + 'px'; inner.style.padding = '0'; }

      var wrapper = document.createElement('div');
      wrapper.style.cssText = 'display:flex;flex-direction:row;flex-wrap:nowrap;align-items:flex-start;';

      for (var c = 0; c < cols; c++) {
        var colUl = document.createElement('ul');
        colUl.style.cssText = [
          'list-style:none', 'margin:0', 'padding:8px 0',
          'flex:0 0 ' + COL_W + 'px',
          'border-right:' + (c < cols - 1 ? '1px solid #f0f0f0' : 'none')
        ].join(';');

        lis.splice(0, MAX_PER_COL).forEach(function(li){ colUl.appendChild(li); });
        wrapper.appendChild(colUl);
      }

      sub.appendChild(wrapper);
    });
  });
})();
</script>
<?php });
