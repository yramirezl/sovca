
function servidor(url, params, metodo)
{
	
  //alert(url+" metodo:"+metodo);
  var resp=new String;
  var modulo = window.location.pathname;
  //alert('modulo: '+modulo);
  var aurl=url.split('?');
  url = aurl[0];
  if (aurl[1])
  {
    params+='&'+aurl[1];
  }
  if(params)
  {
    params+='&url_modulo='+ modulo ;
  }
  else
  {
  	params+='url_modulo='+ modulo ;

  }

    metodo.toUpperCase();
    var resp = '';//ajax de jquery
        $.ajax({
        async:false,
        type: metodo,
        url: url,
        data: params,
        success: function(retorno){
            resp = retorno;
            }
        }); 
        
       // alert(resp);
        
   return resp;
}


function servidor3(url, metodo, data)
{
	
  //alert(url+" metodo:"+metodo);
  var resp=new String;
  var modulo = window.location.pathname;
  //alert('modulo: '+modulo);

    metodo.toUpperCase();
    var resp = '';
    
   //alert(url+' metodo:'+metodo);
    
   new Ajax.Request(url,{
      asynchronous:false,
      method: metodo,
      parameters: data,
      onSuccess: function(respuesta){
      resp=respuesta.responseText;
     }
    }); 
    
      // alert(resp);
        
   return resp;
}

function servidor2(url, metodo)
{
	
  //alert(url+" metodo:"+metodo);
  var resp=new String;
  var modulo = window.location.pathname;
  //alert('modulo: '+modulo);

    metodo.toUpperCase();
    var resp = '';
    
   //alert(url+' metodo:'+metodo);
    
   new Ajax.Request(url,{
      asynchronous:false,
      method: metodo,
      onSuccess: function(respuesta){
      resp=respuesta.responseText;
     }
    }); 
    
      // alert(resp);
        
   return resp;
}


/****************************************************************************
  Funcion para importar los datos de un XML...
****************************************************************************/
/*var xmlDoc;

var moz = (typeof document.implementation != 'undefined') && (typeof document.implementation.createDocument != 'undefined');
var ie = (typeof window.ActiveXObject != 'undefined');

if (moz)
{
    xmlDoc = document.implementation.createDocument("", "", null)
    xmlDoc.async = false;
    xmlDoc.onload = function () {};
}
else if (ie)
{
    xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
    xmlDoc.async = false;
    while(xmlDoc.readyState != 4) {};
}*/
/**********************************************************************
  Eso es para la creacion de un Array en base a un texto XML bien formado
  Estructura retornada:
        RetData[0] = array de parametros

  RetData[0]["campo"] => valor del campo "campo" del registro 0  (1)
  RetData[5]["color"] => parametro asignado al campo "color" del registro 5  (6)

**********************************************************************/
/*function XML2Array(textoXML, rama, filtro)
{

   var aData = new Array;
   var aParams = new Array;

   var uxmlDoc = null;
   try
   {
     xmlDoc.loadXML(textoXML);
	 if(xmlDoc.parseError.errorCode)
	 {
	        alert("Parse XML2Array\nLine:"+ xmlDoc.parseError.line +"\nLine Pos:"+ xmlDoc.parseError.linepos +"\nXML Doc Load Failed\n" + xmlDoc.parseError.srcText);
	        return;
	 }

   }
   catch(e)
   {
     if (document.implementation.createDocument)
     {
       var parser = new DOMParser();
       uxmlDoc = parser.parseFromString(textoXML, "text/xml");
     }else if((xmlDoc == null) && (uxmlDoc == null))
     {
       alert("XML Doc Load Failed");
       return aData;
     }
   }

   var tabla = uxmlDoc.getElementsByTagName("tabla");
   
   if (!rama)
   {
     if (tabla.length > 0) regs = tabla[0].getElementsByTagName("registro");
     else return aData;

     if (!regs) var registros = 0; else var registros = regs.length;

     for (var i=0; i<registros; i++)
     {
       aData[i] = new Array;
       aParams[i] = new Array;

       for (var j=0; j<regs[0].childNodes.length; j++)
       {
         nodo = regs[0].childNodes[j].nodeName;

         objNodo = uxmlDoc.getElementsByTagName(nodo);

         if (objNodo.length)
         {
           var elem = objNodo[i].firstChild;
           if (elem)
           {
             switch (elem.nodeType)
             {
               case 4:
                 valor = elem.data;
                 break;
               case 3:
                 valor = elem.nodeValue;
                 break;
               default:
                 valor = valor_nulo;
             }
           } else valor = valor_nulo;

           aData[i][nodo] = valor;
         }
       }
     }
   }
   else
   {
	   alert('longitud de la tabla: '+tabla.length);
     if (tabla.length > 0) regs = tabla[0].getElementsByTagName(rama);
     else return aData;

     aData[0] = new Array;

     if (!regs) var registros = 0; else var registros = regs.length;

     try
     {
       for (j=0; j<regs[0].childNodes.length; j++)
       {
         nodo = regs[0].childNodes[j].nodeName;
         elem = regs[0].childNodes[j].firstChild;

         if (elem)
         {
           switch (elem.nodeType)
           {
             case 4:
               valor = elem.data;
               break;
             case 3:
               valor = elem.nodeValue;
               break;
             default:
               valor = valor_nulo;
           }
         } else valor = valor_nulo;

         aData[0][nodo] = valor;
       }
     }
     catch(e){}
     finally{}
   }
   return aData;
}

*/
