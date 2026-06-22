<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Tłumaczenie "My Account" → "Moje konto"
add_filter( 'gettext', function( $translated, $original ) {
    $map = [
        'My Account'     => 'Moje konto',
        'My account'     => 'Moje konto',
        'Log In'         => 'Zaloguj się',
        'Log Out'        => 'Wyloguj się',
        'Register'       => 'Zarejestruj się',
    ];
    return $map[ $original ] ?? $translated;
}, 20, 2 );

/**
 * Slider menu — strzałki jako rodzeństwo <ul> bez żadnego wrappera
 * Tylko desktop >= 992px, nie dotykamy struktury Porto
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
    if (items.length <= 5) return;

    // Rodzic <ul> — Porto's .wpb_wrapper.vc_column-inner
    // Dodajemy strzałki jako rodzeństwo <ul>, nie opakowujemy go
    var parent = ul.parentNode;
    if (window.getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }

    function makeBtn(cls, label, html) {
      var b = document.createElement('button');
      b.className = cls;
      b.type = 'button';
      b.setAttribute('aria-label', label);
      b.innerHTML = html;
      return b;
    }

    var btnL = makeBtn('kupnik-nav-prev', 'Poprzednie', '&#8249;');
    var btnR = makeBtn('kupnik-nav-next', 'Następne', '&#8250;');

    // Wstaw strzałki OBOK ul, nie wewnątrz nowego diva
    parent.insertBefore(btnL, ul);
    parent.appendChild(btnR);

    var PER_PAGE = 5, current = 0;

    function show() {
      items.forEach(function(li, i) {
        li.style.display = (i >= current && i < current + PER_PAGE) ? '' : 'none';
      });
      btnL.style.opacity = current === 0 ? '0.3' : '1';
      btnR.style.opacity = (current + PER_PAGE >= items.length) ? '0.3' : '1';
    }

    btnL.addEventListener('click', function(){ if(current > 0){ current--; show(); } });
    btnR.addEventListener('click', function(){ if(current + PER_PAGE < items.length){ current++; show(); } });

    show();
  });
})();
</script>
<?php });
