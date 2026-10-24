<?php
namespace App\Services;
use App\Models\{Cliente,ClienteEstudio,ClienteExperiencia,ClienteHabilidad,ClienteIdioma,ClienteReferencia};
use Illuminate\Database\Eloquent\Collection;
class CvEjemplo {
 public static function cliente(): Cliente {
  $c=new Cliente(['primer_nombre'=>'Lorem','segundo_nombre'=>'Ipsum','primer_apellido'=>'Dolor','segundo_apellido'=>'Sit','pais'=>'NI','nacionalidad'=>'Nicaragüense','profesion'=>'Administración y atención al cliente','perfil_profesional'=>'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Profesional con experiencia en organización de procesos, atención al cliente y trabajo colaborativo. Orientación a resultados, comunicación clara y aprendizaje continuo.','direccion'=>'Ciudad de ejemplo · Dirección demostrativa','telefono'=>'8888 0000','telefono_codigo_pais'=>'+505','correo'=>'lorem.ipsum@example.com','fecha_nacimiento'=>'1998-05-15','estado_civil'=>'soltero','disponibilidad'=>'Inmediata']);
  $c->setRelation('cybers',new Collection());
  $c->setRelation('estudios',new Collection([new ClienteEstudio(['nivel'=>'universidad','titulo'=>'Licenciatura en Administración','institucion'=>'Universidad de Ejemplo','anio'=>2022,'periodo'=>'2018–2022']),new ClienteEstudio(['nivel'=>'curso','titulo'=>'Herramientas digitales y gestión','institucion'=>'Centro de Formación Lorem','anio'=>2024,'periodo'=>'2024'])]));
  $c->setRelation('experiencias',new Collection([new ClienteExperiencia(['cargo'=>'Asistente administrativo','empresa'=>'Organización de Ejemplo','fecha_inicio'=>'2023-01-01','descripcion'=>'Lorem ipsum dolor sit amet. Organización de documentos, seguimiento de solicitudes y elaboración de reportes.']),new ClienteExperiencia(['cargo'=>'Atención al cliente','empresa'=>'Comercio Lorem Ipsum','fecha_inicio'=>'2021-01-01','fecha_fin'=>'2022-12-31','descripcion'=>'Atención personalizada, registro de operaciones y coordinación con el equipo.'])]));
  $c->setRelation('habilidades',new Collection(array_map(fn($s)=>new ClienteHabilidad(['habilidad'=>$s]),['Organización','Comunicación','Excel','Trabajo en equipo','Servicio al cliente','Gestión documental'])));
  $c->setRelation('idiomas',new Collection([new ClienteIdioma(['idioma'=>'Español','nivel'=>'nativo']),new ClienteIdioma(['idioma'=>'Inglés','nivel'=>'intermedio'])]));
  $c->setRelation('referencias',new Collection([new ClienteReferencia(['nombre'=>'Referencia de Ejemplo','detalle'=>'Coordinación administrativa','telefono'=>'+505 8888 0001','correo'=>'referencia@example.com'])]));
  return $c;
 }
}
