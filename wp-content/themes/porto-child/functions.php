<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Menu slider — strzałki lewo/prawo
add_action( 'wp_footer', function() { ?>
<script>
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var nav = document.getElementById('menu-main-menu');
    if (!nav) return;

    // Rodzic <ul> — tu wstawiamy strzałki
    var parent = nav.parentNode;
    // Musi mieć position:relative żeby strzałki działały
    var parentStyle = window.getComputedStyle(parent);
    if (parentStyle.position === 'static') {
      parent.style.position = 'relative';
    }

    var btnL = document.createElement('button');
    var btnR = document.createElement('button');
    btnL.className = 'kupnik-nav-prev';
    btnR.className = 'kupnik-nav-next';
    btnL.setAttribute('aria-label', 'Poprzednie kategorie');
    btnR.setAttribute('aria-label', 'Następne kategorie');
    btnL.innerHTML = '&#8249;';
    btnR.innerHTML = '&#8250;';
    parent.appendChild(btnL);
    parent.appendChild(btnR);

    var step = 220;

    btnL.addEventListener('click', function(e){
      e.preventDefault();
      nav.scrollBy({ left: -step, behavior: 'smooth' });
    });
    btnR.addEventListener('click', function(e){
      e.preventDefault();
      nav.scrollBy({ left: step, behavior: 'smooth' });
    });

    function update(){
      btnL.style.opacity = nav.scrollLeft > 4 ? '1' : '0.25';
      var atEnd = nav.scrollLeft >= nav.scrollWidth - nav.clientWidth - 4;
      btnR.style.opacity = atEnd ? '0.25' : '1';
    }
    nav.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });
})();
</script>
<?php });
