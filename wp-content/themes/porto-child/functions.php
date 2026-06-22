<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Tłumaczenia
add_filter( 'gettext', function( $translated, $original ) {
    $map = [
        'My Account'  => 'Konto',
        'My account'  => 'Konto',
        'Log In'      => 'Zaloguj się',
        'Log Out'     => 'Wyloguj się',
        'Register'    => 'Zarejestruj się',
    ];
    return $map[ $original ] ?? $translated;
}, 20, 2 );

// Slider — strzałki obok <ul>, bez żadnego wrappera
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

    var parent = ul.parentNode;
    if (window.getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }

    // Wstawiamy strzałki jako rodzeństwo <ul>
    var btnL = document.createElement('button');
    var btnR = document.createElement('button');
    btnL.type = btnR.type = 'button';
    btnL.className = 'kupnik-nav-prev'; btnR.className = 'kupnik-nav-next';
    btnL.setAttribute('aria-label','Poprzednie'); btnR.setAttribute('aria-label','Następne');
    btnL.innerHTML = '&#8249;'; btnR.innerHTML = '&#8250;';
    parent.insertBefore(btnL, ul);
    parent.appendChild(btnR);

    var PER = 5, cur = 0;

    function show() {
      items.forEach(function(li, i) {
        li.style.display = (i >= cur && i < cur + PER) ? '' : 'none';
      });
      btnL.style.opacity = cur === 0 ? '0.3' : '1';
      btnR.style.opacity = (cur + PER >= items.length) ? '0.3' : '1';
    }

    btnL.addEventListener('click', function(){ if(cur > 0){ cur--; show(); } });
    btnR.addEventListener('click', function(){ if(cur + PER < items.length){ cur++; show(); } });
    show();
  });
})();
</script>
<?php });
