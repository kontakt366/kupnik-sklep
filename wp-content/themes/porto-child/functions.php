<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'porto-child-style', get_stylesheet_uri(), ['porto-style'] );
});

// Menu slider — dodaj strzałki do głównego menu
add_action( 'wp_footer', function() {
    if ( ! is_admin() ) : ?>
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            var nav = document.querySelector('#header #menu-main-menu');
            if (!nav) return;

            var wrapper = nav.parentElement;
            wrapper.style.position = 'relative';
            wrapper.style.overflow = 'hidden';

            // Przyciski
            var btnL = document.createElement('button');
            var btnR = document.createElement('button');
            btnL.className = 'kupnik-nav-arrow kupnik-nav-prev';
            btnR.className = 'kupnik-nav-arrow kupnik-nav-next';
            btnL.innerHTML = '&#8249;';
            btnR.innerHTML = '&#8250;';
            wrapper.insertBefore(btnL, nav);
            wrapper.appendChild(btnR);

            var step = 200;
            btnL.addEventListener('click', function(e) {
                e.preventDefault();
                nav.scrollBy({ left: -step, behavior: 'smooth' });
            });
            btnR.addEventListener('click', function(e) {
                e.preventDefault();
                nav.scrollBy({ left: step, behavior: 'smooth' });
            });

            function updateArrows() {
                btnL.style.opacity = nav.scrollLeft > 5 ? '1' : '0.3';
                btnR.style.opacity = nav.scrollLeft < (nav.scrollWidth - nav.clientWidth - 5) ? '1' : '0.3';
            }
            nav.addEventListener('scroll', updateArrows);
            updateArrows();
        });
    })();
    </script>
    <?php endif;
});
