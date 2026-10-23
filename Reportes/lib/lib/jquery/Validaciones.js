function DameAjxInst() { 
     var InstAjax=false; 
     try { InstAjax =new ActiveXObject("Msxml2.XMLHTTP"); } 
          catch(e) { 
      try { InstAjax =new ActiveXObject("Microsoft.XMLHTTP"); } 
          catch(E) { 
                 if (!InstAjax && typeof XMLHttpRequest!='undefined') 
                    InstAjax =new XMLHttpRequest(); 
          } 
      } 
return InstAjax;; 
} 
 
/*  Esta función será la resposable de enviar la petición al servidor  */
 
function ValidaUsuario(usuario,correo) { 
   var ajax= DameAjxInst ();
   var mensaje ="invalido";
   expr = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    
    
    if(frmvalusu.usuario.value.length==0)  //¿Tiene 0 caracteres?
    frmvalusu.usuario.focus();    // Damos el foco al control
    alert('No has escrito tu  Nombre de Usuario'); //Mostramos el mensaje
    
    if ( !expr.test(correo) )
        alert("Error: La dirección de correo " + correo + " es incorrecta.");
    return false; //devolvemos el foco
  

   
   // realiza la petición a través de GET  al script de php que creamos previamente
     ajax.open("GET", "valida.php?clave="+usuario + "&correo=" + correo, false); 
     ajax.onreadystatechange=function() { 
     if (ajax.readyState==4) 
        mensaje=ajax.responseText; 
     } 
     ajax.send(null); 
     alert("El usuario es: " + mensaje);
     return null; 
}

/*Validacion formulario*/
function validar(e) { 
tecla = (document.all) ? e.keyCode : e.which; 
if (tecla==8) return true; 
patron =/^[A-Za-z1234567890\s\t]+$/; 
te = String.fromCharCode(tecla); 
return patron.test(te); 
}

function validarSoloLetras(e) { 
tecla = (document.all) ? e.keyCode : e.which; 
if (tecla==8) return true; 
patron =/^[A-Za-z\s\t]+$/; 
te = String.fromCharCode(tecla); 
return patron.test(te); 
}

function validarSoloNumeros(e) { 
tecla = (document.all) ? e.keyCode : e.which; 
if (tecla==8) return true; 
patron =/^[1234567890\t]+$/; 
te = String.fromCharCode(tecla); 
return patron.test(te); 
}

function validarSoloMontos(e) { 
tecla = (document.all) ? e.keyCode : e.which; 
if (tecla==8) return true; 
patron =/^[1234567890.\t]+$/; 
te = String.fromCharCode(tecla); 
return patron.test(te); 
}

function validarCedulaDependiente(e) { 
tecla = (document.all) ? e.keyCode : e.which; 
if (tecla==8) return true; 
patron =/^[1234567890\t]+$/; 
te = String.fromCharCode(tecla); 
return patron.test(te); 
}
