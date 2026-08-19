// Menu Functions
function e_storefront_openNav() {
  jQuery(".sidenav").addClass('show');
}

function e_storefront_closeNav() {
  jQuery(".sidenav").removeClass('show');
}

/////////////////////// Focus handling ///////////////////////
(function(window, document) {
  function e_storefront_handleMobileMenuNavigation() {
    document.addEventListener('keydown', function(e) {
      if (window.innerWidth > 991) return;
      const nav = document.querySelector('.sidenav.show');
      if (!nav) return;
      const focusableElements = Array.from(nav.querySelectorAll(
        'a, button, [tabindex="0"], input, [tabindex]:not([tabindex="-1"])'
      )).filter(el => el.offsetParent !== null);

      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      const activeElement = document.activeElement;

      if (e.key === 'Tab') {
        if (!e.shiftKey && activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        } 
        else if (e.shiftKey && activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
        else if (!nav.contains(activeElement)) {
          e.preventDefault();
          firstElement.focus();
        }
        return;
      }

      if (e.key === 'Tab' && e.shiftKey) {
        const activeElement = document.activeElement;

        if (activeElement.closest('.dropdown-menu')) {
          e.preventDefault();
          
          //current submenu
          const currentSubmenu = activeElement.closest('.dropdown-menu');
          const submenuItems = Array.from(currentSubmenu.querySelectorAll('a, button, [tabindex="0"]'))
            .filter(el => el.offsetParent !== null);
          const currentIndex = submenuItems.indexOf(activeElement);
          if (currentIndex > 0) {
            submenuItems[currentIndex - 1].focus();
          } else {
            const parentDropdown = currentSubmenu.closest('.dropdown, .page_item_has_children');
            if (parentDropdown) {
              // Find all focusable elements in parent
              const allFocusable = Array.from(parentDropdown.querySelectorAll('a, button, [tabindex="0"]'))
                .filter(el => el.offsetParent !== null);
              
              // Filter to only direct children of parentDropdown
              const parentItems = allFocusable.filter(el => el.parentElement === parentDropdown);
              
              if (parentItems.length > 0) {
                parentItems[0].focus();
              }
            }
          }
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    e_storefront_handleMobileMenuNavigation();

    document.addEventListener('focusin', function(e) {
      if (window.innerWidth > 991) return;
      
      const focusedItem = e.target;
      const submenu = focusedItem.closest('.dropdown-menu');
      if (submenu) {
        submenu.style.display = 'block';
        submenu.classList.add('show');
      }
    });
  });
})(window, document);

jQuery(document).ready(function ($) {
  /*--- adding dropdown class to menu -----*/
$("#site-navigation ul.sub-menu,#site-navigation ul.children").parent().addClass("dropdown");
  $("#site-navigation ul.sub-menu,#site-navigation ul.children").addClass("dropdown-menu");
  $("#site-navigation ul#menuid li.dropdown a,#site-navigation ul.children li.dropdown a").addClass("dropdown-toggle");
  $("#site-navigation ul.sub-menu li a,#site-navigation ul.children li a").removeClass("dropdown-toggle");
  $('#site-navigation nav li.dropdown > a,#site-navigation .page_item_has_children a').append('<span class="caret"></span>');
  $('#site-navigation a.dropdown-toggle').attr('data-toggle', 'dropdown');

  /-- Mobile menu --/
  if ($('#site-navigation').length) {
    $('#site-navigation .menu li.dropdown, #site-navigation li.page_item_has_children').append(function () {
      // Changed Bootstrap icon to Dashicon (arrow down)
      return '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
    });

    $('#site-navigation .menu li.dropdown .dashicons, #site-navigation li.page_item_has_children .dashicons').on('click', function () {
      $(this).parent('li').children('ul').slideToggle().toggleClass('show');
    });
  }

  $(window).on('scroll', function () {
    if ($(this).scrollTop() > 200)
      $('.btntoTop').addClass('active');
    else
      $('.btntoTop').removeClass('active');
  });

  /*-- Reload page when width is between 320 and 768px and only from desktop */
  var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent) ? true : false;
  $(window).on('resize', function () {
    var win = $(this); //this = window
    if (win.width() > 320 && win.width() < 991 && isMobile == false && !$("body").hasClass("elementor-editor-active")) {
      location.reload();
    }
  });
});


/////////////////////// end ///////////////////////