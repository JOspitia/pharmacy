Implementación de Funcionalidad

Se requiere desarrollar un sistema farmaceutico con el fin de poder gestionar el stock de productos en bodegas, 
para ello tengo una idea para el modelo de productos: 

1. Tengo que tener los siguientes campos:
 -nombre
 -CUM
 -codigo de barras
 -Registro Invima
 -Rango de vencimiento
 
En base a esto, necesito saber adicional, fecha de vencimiento y rango de vencimiento, lote, grupo, bodega y stock;
para esto es necesario adicionar los formularios crud, para cada los componeste bodega, grupo y lote.

2. Adiciono las siguientes especificaciones:
-Búsqueda textual input text Busca por nombre, CUM, código de barras o Invima
-Bodega select Filtra productos disponibles en una bodega específica
-Grupo select Filtra por grupo de producto (medicamentos, insumos, etc.)
-Estado lote select "Con stock", "Por vencer (30 días)", "Vencido", "Todos"
-Rango vencimiento date range Productos cuyo lote vence entre dos fechas

3. Necesito desarrollar el modulo de bodegas y que tenga las siguientes funcionalidades:
-Crud para crear, actualizar, eliminar y listar bodegas
-la opción de activar o inactivar la bodega

4. Se necesita llevar un registro de temperatura y humedad de cada bodega donde se almacenan
medicamentos. Cada bodega puede tener múltiples sensores. Se debe poder consultar el
histórico y generar una alerta si la temperatura supera los 25°C o la humedad supera el 70%.
Para ello:

-Se debe de implementar un modulo adicional dentro de bodegas donde se pueda hacer crud de sensores. 
(estas solo se podran visualizar, no se podran eliminar, 
el registro solo se realizara de forma automatica por debajo, no desde el formulario)
-Registro de temperatura con proceso automatico simulando un sensor para tener datos de prueba.
-Endpoint para consultar las últimas N lecturas de una bodega con filtro por sensor
-Evento/Listener en Laravel que dispare la alarma automáticamente al registrar una lectura fuera de rango
-Las lecturas al registrarse deben de tener la fecha de registro y la hora.
-Debe de haber una tabla de registros del sensor un modulo que los liste

5. Necesito desarrolladora una crud para parametrizar los lotes de los productos 
-Nombre del lote
-Descripción
-Estado (Activo/Inactivo)

6. Necesito desarrolladora una crud para parametrizar los grupos de los productos 
-Nombre del grupo
-Descripción
-Estado (Activo/Inactivo)
