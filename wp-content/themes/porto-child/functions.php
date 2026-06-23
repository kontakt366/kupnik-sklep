<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

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

add_action( 'wp_footer', function() { ?>
<script>
(function(){
  if (window.innerWidth < 992) return;

  window.addEventListener('load', function(){
    var ul = document.getElementById('menu-main-menu');
    if (!ul) return;

    /* ---- 1. WIELOKOLUMNOWE PODMENU ----
       Uruchamia się PRZED sliderem — wszystkie li są widoczne, getBoundingClientRect OK.
       Porto: li > div.popup > div.inner > ul.sub-menu */

    var MIN_PER_COL = 7;   // min itemów w kolumnie (próg wejścia)
    var MAX_COLS    = 5;   // maks kolumn → maks szerokość popupu = 5 × 200 = 1000px
    var COL_W       = 200; // px szerokości kolumny

    Array.from(ul.children).forEach(function(topLi) {
      if (!topLi.classList.contains('menu-item')) return;

      var sub = topLi.querySelector('.popup .inner ul.sub-menu');
      if (!sub || sub.dataset.kupnikCols) return;

      var lis = Array.from(sub.children);
      if (lis.length <= MIN_PER_COL) return;

      sub.dataset.kupnikCols = '1';

      /* Oblicz liczbę kolumn i items/kolumna — nigdy więcej niż MAX_COLS kolumn */
      var cols   = Math.min(MAX_COLS, Math.ceil(lis.length / MIN_PER_COL));
      var perCol = Math.ceil(lis.length / cols);
      var totalW = cols * COL_W;

      topLi.classList.remove('narrow');

      var popup = topLi.querySelector('.popup');
      var inner = topLi.querySelector('.popup .inner');

      if (popup) {
        popup.style.setProperty('min-width', totalW + 'px', 'important');
      }
      if (inner) { inner.style.padding = '0'; }

      /* Mouseenter: pozycjonowanie + re-aplikacja stylu przez rAF.
         Porto resetuje style popupa w swoim hover handlerze — rAF uruchamia się
         po WSZYSTKICH synchronicznych handlerach zdarzenia, przed malowaniem. */
      if (popup) {
        (function(tLi, pop, inn, w) {
          function applyPopupStyles() {
            requestAnimationFrame(function() {
              var liLeft  = tLi.getBoundingClientRect().left;
              var vw      = window.innerWidth;
              var leftPos = 0;
              if (liLeft + w > vw - 10) { leftPos = vw - 10 - w - liLeft; }
              leftPos = Math.max(leftPos, 10 - liLeft);

              pop.style.setProperty('left',          leftPos + 'px',               'important');
              pop.style.setProperty('right',         'auto',                       'important');
              pop.style.setProperty('min-width',     w + 'px',                    'important');
              pop.style.setProperty('background',    '#fff',                       'important');
              pop.style.setProperty('border',        '1px solid #e8e8e8',         'important');
              pop.style.setProperty('border-top',    '3px solid #FF6B35',         'important');
              pop.style.setProperty('box-shadow',    '0 8px 24px rgba(0,0,0,.1)', 'important');
              pop.style.setProperty('border-radius', '0 0 4px 4px',              'important');
              if (inn) inn.style.setProperty('background', '#fff', 'important');
            });
          }
          tLi.addEventListener('mouseenter', applyPopupStyles);
        })(topLi, popup, inner, totalW);
      }

      /* Buduj kolumny wewnątrz ul.sub-menu */
      var wrapper = document.createElement('div');
      wrapper.style.cssText = 'display:flex;flex-direction:row;flex-wrap:nowrap;align-items:flex-start;background:#fff;';

      for (var c = 0; c < cols; c++) {
        var colUl = document.createElement('ul');
        colUl.style.cssText = [
          'list-style:none', 'margin:0', 'padding:8px 0',
          'flex:0 0 ' + COL_W + 'px',
          'border-right:' + (c < cols - 1 ? '1px solid #f0f0f0' : 'none')
        ].join(';');
        lis.splice(0, perCol).forEach(function(li) { colUl.appendChild(li); });
        wrapper.appendChild(colUl);
      }
      sub.appendChild(wrapper);
    });

    /* ---- 2. SLIDER ---- */
    var items = Array.from(ul.children).filter(function(li) {
      return li.classList.contains('menu-item');
    });
    var PER = window.innerWidth >= 1200 ? 5 : 4, cur = 0;

    if (items.length > PER) {
      var parent = ul.parentNode; // .wpb_wrapper.vc_column-inner — kolumna flex, nie cały header
      if (window.getComputedStyle(parent).position === 'static') {
        parent.style.position = 'relative';
      }

      /* parent.clientWidth = szerokość kolumny flex (ok. 800px), nie ula.
         Porto robi ul.mega-menu absolutnym/pełnej szerokości, więc ul.clientWidth
         zwraca ~1536px — dlatego używamy parent, nie ul. */
      var itemW = Math.floor((parent.clientWidth - 56) / PER); // 56 = padding strzałek L+R

      var btnL = document.createElement('button');
      var btnR = document.createElement('button');
      btnL.type = btnR.type = 'button';
      btnL.className = 'kupnik-nav-prev';
      btnR.className = 'kupnik-nav-next';
      btnL.innerHTML = '&#8249;';
      btnR.innerHTML = '&#8250;';
      parent.insertBefore(btnL, ul);
      parent.appendChild(btnR);

      function show() {
        items.forEach(function(li, i) {
          if (i >= cur && i < cur + PER) {
            li.style.display  = '';
            li.style.maxWidth = itemW + 'px'; // cap — nie pozwól itemowi urosnąć szerzej niż slot
          } else {
            li.style.display  = 'none';
            li.style.maxWidth = '';
          }
        });
        btnL.style.opacity = (cur === 0)                 ? '0.3' : '1';
        btnR.style.opacity = (cur + PER >= items.length) ? '0.3' : '1';
      }
      btnL.addEventListener('click', function() { if (cur > 0)                 { cur--; show(); } });
      btnR.addEventListener('click', function() { if (cur + PER < items.length) { cur++; show(); } });
      show();
    }
  });
})();
</script>
<?php });
