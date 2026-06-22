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

  document.addEventListener('DOMContentLoaded', function(){
    var ul = document.getElementById('menu-main-menu');
    if (!ul) return;

    /* ---- 1. SLIDER ---- */
    var items = Array.from(ul.children).filter(function(li){
      return li.classList.contains('menu-item');
    });
    var PER = 4, cur = 0; // 4 naraz — długie polskie nazwy nie mieszczą się po 5

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
    // JavaScript tworzy kolumny — niezawodne, Porto nie nadpisuje
    var MAX_PER_COL = 7;

    ul.querySelectorAll(':scope > li > ul.sub-menu').forEach(function(sub){
      var lis = Array.from(sub.children);
      if (lis.length <= MAX_PER_COL) return;

      var cols = Math.ceil(lis.length / MAX_PER_COL);

      // Wrapper flex dla kolumn
      sub.style.cssText += [
        'display:flex !important',
        'flex-direction:row !important',
        'flex-wrap:nowrap !important',
        'align-items:flex-start !important',
        'width:' + (cols * 210) + 'px !important',
        'padding:0 !important'
      ].join(';');

      // Przenieś <li> do osobnych divów-kolumn
      for (var c = 0; c < cols; c++) {
        var colUl = document.createElement('ul');
        colUl.style.cssText = [
          'list-style:none', 'margin:0', 'padding:8px 0',
          'min-width:210px', 'flex:0 0 210px',
          'border-right:' + (c < cols-1 ? '1px solid #f0f0f0' : 'none')
        ].join(';');

        var slice = lis.splice(0, MAX_PER_COL);
        slice.forEach(function(li){ colUl.appendChild(li); });
        sub.appendChild(colUl);
      }
    });

  });
})();
</script>
<?php });
