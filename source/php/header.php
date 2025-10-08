<header class="header">
        <button class="menu-btn" id="menuBtn" aria-label="Menü öffnen" aria-controls="mobileDrawer" aria-expanded="false">
                <span></span><span></span><span></span>
        </button>
        <a class="header-logo" href="/">Abi26</a>
        <nav class="header-nav">
                <?php if (!empty($_SESSION['loggedIn'])) { ?>
                     <a href="/account.php">Account</a>
                <?php } else { ?>
                     <a href="/account.php">Anmelden / Registrieren</a>
                <?php } ?>
        
        </nav>
</header>

<div class="backdrop" id="backdrop" hidden></div>
<aside class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
        <div class="drawer-header">
                <span>Menü</span>
                <button class="close-btn" id="closeDrawer" aria-label="Menü schließen">×</button>
        </div>
        <nav class="drawer-nav">
                <a href="/">Home</a>
                <?php if (!empty($_SESSION['loggedIn'])) { ?>
                     <a href="/account.php">Account</a>
                <?php } else { ?>
                     <a href="/account.php">Anmelden / Registrieren</a>
                <?php } ?>
        </nav>
</aside>

<script>
(function(){
    const btn = document.getElementById('menuBtn');
    const drawer = document.getElementById('mobileDrawer');
    const backdrop = document.getElementById('backdrop');
    const close = document.getElementById('closeDrawer');

    function open(){
        drawer.classList.add('open');
        backdrop.hidden = false;
        btn.setAttribute('aria-expanded','true');
        drawer.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer(){
        drawer.classList.remove('open');
        backdrop.hidden = true;
        btn.setAttribute('aria-expanded','false');
        drawer.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
    }
    
    btn && btn.addEventListener('click', open);
    close && close.addEventListener('click', closeDrawer);
    backdrop && backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e)=>{
        if(e.key === 'Escape') closeDrawer();
    });
})();
</script>