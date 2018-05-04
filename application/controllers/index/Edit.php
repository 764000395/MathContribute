<?php
/**
 * 编辑后台操作页面
 * @authors 杨志颖 (764000395@qq.com)
 * @date    2018-05-03 17:26:06
 * @version $Id$
 */

class Edit extends MY_Controller {

	function __construct() {
		parent::__construct();
		if ($this->session->userdata('identity') != 'edit') {
			alert_msg('请以编辑身份登录系统', 'go', site_url('home/login'));
		}
		$this->load->model('index_model');
	}

	/*
		稿件列表  类似Author.php的list_article()方法
	 */
	public function list_article($type, $offset = 0) {
		$where_arr = array();
		$view_html = 'editorial/list_article.html';
		switch ($type) {
		case 'all': //全部稿件
			$where_arr = array();
			break;
		case 'wait_check': //未完成审核 != -1 && <3
			$where_arr = array('check_status !=' => '-1', 'check_status <' => 3);
			break;
		case 'use': //录用稿件 =3
			$where_arr = array('check_status' => '3');
			break;
		case 'refuses': //被拒稿件 =-1
			$where_arr = array('check_status' => '-1');
			break;
		case 'edit': //返修稿件
			$where_arr = array('check_status <' => '-1');
			break;
		case 'assign_first': //指定初审
			$where_arr = array('check_status' => 0, 'allot_status' => 0);
			$view_html = 'editorial/list_article_check.html';
			$data['assign_rank'] = 'assign_first';
			break;
		case 'assign_second': //指定复审
			$where_arr = array('check_status' => 1, 'allot_status' => 0);
			$view_html = 'editorial/list_article_check.html';
			$data['assign_rank'] = 'assign_second';
			break;
		case 'select': //编委会定稿
			$where_arr = array('check_status' => 2);
			$view_html = 'edit/list_article_select.html';
			break;
		case 'doubt': //疑问稿件 -10=>初审疑问稿件 -11=>复审疑问稿件
			$where_arr = array('check_status <' => '-9');
			$view_html = 'editorial/list_article_doubt.html';
			$data['assign_rank'] = 'doubt';
			break;
		}
		$per_page = 10; //每页显示10条
		$page_url = site_url('index/editorial/list_article/' . $type); //分页地址url
		$total_rows = $this->db->where($where_arr)->count_all_results('article'); //共多少条数据
		$offset_uri_segment = 5;

		//获取分页
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$data['total_rows'] = $total_rows;

		//获取文章列表信息
		$other_info = ', check_deadline'; //其他要获取的字段 以 "," 开头
		$data['article'] = $this->index_model->get_list_article($where_arr, $offset, $per_page, $other_info);
		$this->load->view($view_html, $data);
	}

	/*
		查看文章
	 */
	public function article($action, $article_id) {
		if (!is_numeric($article_id)) {
			alert_msg('无权访问');
		}
		$where_arr = array('article_id' => $article_id);
		$status = $this->index_model->get_info_article($where_arr, '');
		empty($status) ? alert_msg('该稿件已被删除') : $data['article'] = $status[0];
		switch ($action) {
		case 'assign_first': //指定初审
			$view_html = 'editorial/assign_article.html';
			$where_arr = array('identity' => 'specialist', 'status' => 1);
			$data['specialist'] = $this->index_model->get_specialist_info($where_arr);
			$data['suggest'] = array();
			break;
		case 'assign_second': //指定复审
			$view_html = 'editorial/assign_article.html';

			//获取专家名字
			$where_arr = array('identity' => 'specialist', 'status' => 1);
			$data['specialist'] = $this->index_model->get_specialist_info($where_arr);

			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		case 'select':
			$view_html = 'edit/select_editorial.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			$data['editorial'] = $this->index_model->get_user_list();
			break;
		case 'doubt':
			$view_html = 'editorial/doubt_article.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		default:
			$view_html = 'editorial/info_article.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		}

		$this->load->view($view_html, $data);
	}

	/*
		查看作者信息
	 */
	public function see_user($user_id) {
		$user = $this->index_model->show_user_info($this->session->userdata('user_id'));
		if (empty($user)) {
			alert_msg('该用户不存在！');
		}
		$data = $user[0];
		$this->load->view('edit/user_info.html', $data);
	}

}