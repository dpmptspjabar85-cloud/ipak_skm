<<<<<<< HEAD



$( document ).ready(function() {
    var arr = ['bg_1.jpg','bg_2.jpg','bg_3.jpg'];
    
    var i = 0;
    setInterval(function(){
        if(i == arr.length - 1){
            i = 0;
        }else{
            i++;
        }
        var img = 'url(../assets/images/'+arr[i]+')';
        $(".full-bg").css('background-image',img); 
     
    }, 4000)

});



document.addEventListener('DOMContentLoaded', function(){
  const trigger = document.getElementById('navTrigger');
  const overlay = document.getElementById('main-nav');
  const panel   = overlay && overlay.querySelector('.nav-panel');
  const closeBtn= overlay && overlay.querySelector('.nav-close');

  if (!trigger || !overlay || !panel) return console.warn('Elemen wajib tidak lengkap.');

  function openNav(){
    document.body.classList.add('nav-open','nav-locked');
    overlay.setAttribute('aria-hidden','false');
    (panel.querySelector('a') || closeBtn).focus?.({preventScroll:true});
  }
  function closeNav(){
    document.body.classList.remove('nav-open','nav-locked');
    overlay.setAttribute('aria-hidden','true');
    trigger.focus?.({preventScroll:true});
  }
  function toggleNav(e){ e.preventDefault(); 
    document.body.classList.contains('nav-open') ? closeNav() : openNav();
  }

  trigger.addEventListener('click', toggleNav, {passive:false});
  trigger.addEventListener('touchend', toggleNav, {passive:false});

  // klik backdrop (area di luar panel) menutup
  overlay.addEventListener('click', function(e){ if (!panel.contains(e.target)) closeNav(); });

  // klik tombol X menutup
  closeBtn.addEventListener('click', closeNav);

  // ESC menutup
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeNav(); });
=======



$( document ).ready(function() {
    var arr = ['bg_1.jpg','bg_2.jpg','bg_3.jpg'];
    
    var i = 0;
    setInterval(function(){
        if(i == arr.length - 1){
            i = 0;
        }else{
            i++;
        }
        var img = 'url(../assets/images/'+arr[i]+')';
        $(".full-bg").css('background-image',img); 
     
    }, 4000)

});



document.addEventListener('DOMContentLoaded', function(){
  const trigger = document.getElementById('navTrigger');
  const overlay = document.getElementById('main-nav');
  const panel   = overlay && overlay.querySelector('.nav-panel');
  const closeBtn= overlay && overlay.querySelector('.nav-close');

  if (!trigger || !overlay || !panel) return console.warn('Elemen wajib tidak lengkap.');

  function openNav(){
    document.body.classList.add('nav-open','nav-locked');
    overlay.setAttribute('aria-hidden','false');
    (panel.querySelector('a') || closeBtn).focus?.({preventScroll:true});
  }
  function closeNav(){
    document.body.classList.remove('nav-open','nav-locked');
    overlay.setAttribute('aria-hidden','true');
    trigger.focus?.({preventScroll:true});
  }
  function toggleNav(e){ e.preventDefault(); 
    document.body.classList.contains('nav-open') ? closeNav() : openNav();
  }

  trigger.addEventListener('click', toggleNav, {passive:false});
  trigger.addEventListener('touchend', toggleNav, {passive:false});

  // klik backdrop (area di luar panel) menutup
  overlay.addEventListener('click', function(e){ if (!panel.contains(e.target)) closeNav(); });

  // klik tombol X menutup
  closeBtn.addEventListener('click', closeNav);

  // ESC menutup
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeNav(); });
>>>>>>> 563b877ee5432943018f22402774054db6dabfa4
});