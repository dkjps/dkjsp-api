<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';
use Restserver\Libraries\REST_Controller;

class AktivitasApiController extends REST_Controller {
  function __construct($config = 'rest') {
    parent::__construct($config);
    date_default_timezone_set("Asia/Jakarta");
    $this->dateToday = date("Y-m-d");
    $this->timeToday = date("h:i:s");
    $this->load->model("GeneralApiModel");
  }

  function dataAktivitas_post(){
    $user = array(
      'id' => $this->input->post('id_user')
    );

    if(!empty($user['id'])){
      $user = $this->GeneralApiModel->getWhereTransactional($user, "user_provinsi_kota")->row();
      if(!empty($user)){
        $aktivitas = $this->GeneralApiModel->getWhereMaster(array('statusdata'=>0), "masterdata_aktivitas")->result();

        $list_aktivitas = array();
        $list_soal = array();
        $list_jawaban = array();

        foreach ($aktivitas as $kd => $vd) {
          $soal = $this->GeneralApiModel->getWhereMaster(array('id_aktivitas'=>$vd->id), "masterdata_soal_aktivitas")->result();
          foreach ($soal as $ks => $vs) {
            $jawaban = $this->GeneralApiModel->getWhereMaster(array('id_soal'=>$vs->id), "masterdata_pilihan_jawaban_aktivitas")->result();
            foreach ($jawaban as $kj => $vj) {
              array_push($list_jawaban, array("id_jawaban"=>$vj->id, "jawaban"=>$vj->jawaban));
            }
            array_push($list_soal, array("id_soal"=>$vs->id, "soal"=>$vs->soal, "tipe"=>$vs->tipe, "data_jawaban"=>$list_jawaban));
            $list_jawaban = array();
          }
          array_push($list_aktivitas, array("id_aktivitas"=>$vd->id, "nama"=>$vd->nama, "list_soal"=>$list_soal));
          $list_soal = array();
        }

        $this->response(array('status' => 200, 'message' => 'Sukses', 'data' => $list_aktivitas));
      }else{
        $this->response(array('status' => 200, 'message' => 'Data User tidak ditemukan', 'data' => null));
      }
    }else{
      $this->response(array('status' => 200, 'message' => 'Masukkan id user terlebih dahulu! data tidak ditemukan', 'data' => null));
    }
  }

  function submitAktivitas_post(){
    $data = json_decode($this->input->raw_input_stream, TRUE);
    $data_jawaban = $data["data_jawaban"];
    $id_skrining = $data["id_skrining"];
    $id_user = $data["id_user"];

    $user = $this->GeneralApiModel->getWhereTransactional(array('id' => $id_user),'transactional_user')->result();
    $bulan = $this->getAge($user[0]->tgl_lahir);

    if(!empty($data_jawaban) && !empty($id_skrining) && !empty($id_user)){
      //medapatkan data sub skrining
      $query = $this->GeneralApiModel->getWhereMaster(array('id_skrining' => $id_skrining),'masterdata_sub_skrining')->result();
      //dapatkan jumlah soal tiap sub skrining
      $id_jawaban = array();
      foreach ($data_jawaban as $v) {
        array_push($id_jawaban, $v['id_jawaban']);
      }

      // DEMOGRAFI DAN FISIK
      $fisik = 0;

      // ORANG SEHAT
      $sehat = array(38,48,52,54,56);
      if (count(array_intersect($id_jawaban, $sehat))==count($sehat)) {
        $fisik = 1;
      }

      // PELAKU PERJALANAN
      if (in_array(49, $id_jawaban)) {
        $fisik = 2;
      }

      // KONTAK ERAT
      if (in_array(37, $id_jawaban) && (in_array(51, $id_jawaban) || in_array(53, $id_jawaban) ||in_array(55, $id_jawaban))) {
        $fisik = 3;
      }

      // KASUS SUSPEK
      $a_h = array(25,27,29,31,33,35,37,122);
      if (in_array(47, $id_jawaban) && (count(array_intersect($id_jawaban, $a_h)) > 0)) {
        $fisik = 4;
      }

      // KASUS SUSPEK
      if (in_array(49, $id_jawaban) && (count(array_intersect($id_jawaban, $a_h)) > 0)) {
        $fisik = 4;
      }

      // KASUS SUSPEK
      $empat_a_c = array(51,53,55);
      if ((count(array_intersect($id_jawaban, $empat_a_c)) > 0) && (count(array_intersect($id_jawaban, $a_h)) > 0)) {
        $fisik = 4;
      }

      // KASUS PROBABLE
      $satu_a_g = array(25,27,29,31,33,35,122);
      if (in_array(47, $id_jawaban) && (count(array_intersect($id_jawaban, $satu_a_g)) > 0) && in_array(39, $id_jawaban) && in_array(41, $id_jawaban)) {
        $fisik = 5;
      }

      // KASUS PROBABLE
      if (in_array(49, $id_jawaban) && (count(array_intersect($id_jawaban, $satu_a_g)) > 0) && in_array(39, $id_jawaban) && in_array(41, $id_jawaban)) {
        $fisik = 5;
      }

      // KASUS PROBABLE
      if ((count(array_intersect($id_jawaban, $a_h)) > 0) && (count(array_intersect($id_jawaban, $satu_a_g)) > 0) && in_array(39, $id_jawaban) && in_array(41, $id_jawaban)) {
        $fisik = 5;
      }

      // KASUS KONFIRMASI
      $enam_c_e = array(61,62,63);
      if ((count(array_intersect($id_jawaban, $enam_c_e)) > 0)) {
        $fisik = 6;
      }

      // KOMORBID
      $tidak = array(3,11,24,26,28,30,32,34,36,38,40,42,44,46,48,50,52,54,56,58,59,124);
      if (in_array(43, $id_jawaban) && (count(array_intersect($id_jawaban, $tidak))==count($tidak))) {
        $fisik = 7;
      }

      // HAMIL
      $hamil = array(4,5,6);
      if ((count(array_intersect($id_jawaban, $hamil)) > 0) && (count(array_intersect($id_jawaban, $tidak))==count($tidak))) {
        $fisik = 12;
      }

      // TENAGA KESEHATAN
      if (in_array(14, $id_jawaban) && (count(array_intersect($id_jawaban, $tidak))==count($tidak))){
        $fisik = 13;
      }

      // DISABILITAS MENTAL
      if (in_array(22, $id_jawaban) && (count(array_intersect($id_jawaban, $tidak))==count($tidak))){
        $fisik = 14;
      }


      $psikososial = 0;
      // PSIKOSOSIAL
      if ($bulan > 180) {
        $satu_duapuluh = range(64,102,2);
        if (count(array_intersect($id_jawaban, $satu_duapuluh))>=10){
          $psikososial = 8;
        }

        if (in_array(104, $id_jawaban)){
          $psikososial = 9;
        }

        $duadua_duaempat = array(106,108,110);
        if (count(array_intersect($id_jawaban, $duadua_duaempat))>0){
          $psikososial = 10;
        }

        $dualima_duasembilan = array(112,114,116,118,120);
        if (count(array_intersect($id_jawaban, $dualima_duasembilan))>0){
          $psikososial = 11;
        }

      } else {
        if ($bulan < 18) {
          $psikososial = 16;
        } elseif ($bulan < 36) {
          $psikososial = 17;
        } elseif ($bulan < 72) {
          $psikososial = 18;
        } elseif ($bulan < 144) {
          $psikososial = 19;
        } elseif ($bulan < 180) {
          $psikososial = 20;
        }
      }

      if ($fisik==0) {
        $return_fisik = "Data Tidak Ditemukan";
      } else {
        $return_fisik = $this->GeneralApiModel->getWhereMaster(array('id' => $fisik),'masterdata_grading_status_kesehatan')->result();
        $return_fisik = $return_fisik[0]->nama;
      }

      if ($psikososial==0) {
        $return_psikososial = "Data Tidak Ditemukan";
      } else {
        $return_psikososial = $this->GeneralApiModel->getWhereMaster(array('id' => $psikososial),'masterdata_grading_status_kesehatan')->result();
        $return_psikososial = $return_psikososial[0]->nama;
      }

      $return['kondisi_fisik'] = $return_fisik;
      $return['kondisi_mental'] = $return_psikososial;

      $this->response(array('status' => 200, 'message' => 'test', 'data' => $return));
    }else{
      $this->response(array('status' => 200, 'message' => 'Masukkan id skrining, id user, dan data jawaban terlebih dahulu! data tidak ditemukan', 'data' => null));
    }
  }

  function getAge($date){
       $dob = new DateTime($date);
       $now = new DateTime();

       $datetime1 = date_create($dob->format('Y-m-d'));
       $datetime2 = date_create($now->format('Y-m-d'));

       $interval = date_diff($datetime1, $datetime2);

       $month= $interval->format('%m');
       $year= $interval->format('%y');
       $total = ($year*12) + $month;

       return $total;
   }
}