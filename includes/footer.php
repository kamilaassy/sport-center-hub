<?php // includes/footer.php ?>
</main><!-- /.container — flex:1 pushes footer down -->

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="brand-icon" style="width:26px;height:26px;font-size:.8rem;">⬡</div>
      Sport Center Hub
    </div>
    <span>© <?= date('Y') ?> Sport Center Hub · All rights reserved</span>
    <?php if (!isLoggedIn()): ?>
      <a href="login.php" style="color:#94a3b8;font-size:.8rem;">Login</a>
    <?php endif; ?>
  </div>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
