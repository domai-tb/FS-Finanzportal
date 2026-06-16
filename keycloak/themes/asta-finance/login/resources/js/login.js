document.addEventListener('DOMContentLoaded', function(){
  const pw = document.getElementById('password');
  const btn = document.getElementById('show-password');
  if(btn && pw){
    btn.addEventListener('click',()=>{
      if(pw.type === 'password'){
        pw.type = 'text';
        btn.textContent = 'Verbergen';
        btn.setAttribute('aria-label', 'Passwort verbergen');
      } else {
        pw.type = 'password';
        btn.textContent = 'Anzeigen';
        btn.setAttribute('aria-label', 'Passwort anzeigen');
      }
    });
  }
});
