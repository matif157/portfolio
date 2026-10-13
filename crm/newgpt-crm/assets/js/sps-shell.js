
(function(){
  const side = document.querySelector('.sps-sidebar');
  const toggle = document.getElementById('spsSidebarToggle');
  const mobile = document.getElementById('spsMobileMenu');
  if(toggle && side){
    toggle.addEventListener('click', ()=>{
      side.classList.toggle('is-collapsed');
      document.body.classList.toggle('sps-sidebar-collapsed');
      localStorage.setItem('sps_sidebar_collapsed', side.classList.contains('is-collapsed')?'1':'0');
    });
    if(window.innerWidth>980 && localStorage.getItem('sps_sidebar_collapsed')==='1'){
      side.classList.add('is-collapsed');
      document.body.classList.add('sps-sidebar-collapsed');
    }
  }
  if(mobile && side) mobile.addEventListener('click',()=>side.classList.toggle('open'));
})();
