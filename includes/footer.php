<?php
// Alte Formulareingaben werden nach dem Rendern verworfen, damit sie nicht
// beim naechsten Seitenaufruf erneut auftauchen.
clearOldInput();
?>
</main>

<footer class="site-footer">
    <p><?= e(APP_NAME) ?> &middot; lokale XAMPP-Umgebung</p>
</footer>
</body>
</html>
