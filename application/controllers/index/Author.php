<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * 个人中心
 */
class Author extends MY_Controller {
	public function __construct() {
		parent::__construct();
		if ($this->session->userdata('identity') != 'author') {
			alert_msg('请以作者身份登录系统', 'go', site_url('home/login'));
		}
		$this->load->model('index_model');
	}

	/*
		用户在线投稿
	 */
	public function contribute($action = 'see', $article_id = '') {
		if ($action == 'do') {
			//上传文件配置
			$config = array(
				'upload_path' => './uploads/attachment/',
				'allowed_types' => 'doc|docx|pdf|tex|ctex', //限制稿件格式，暂定
				'file_name' => time() . mt_rand(1000, 9999),
			);
			$this->load->library('upload', $config);
			$attachment_url = ''; //稿件上传地址
			//判断文件是否上传成功，否则返回错误提示信息
			if (!$this->upload->do_upload('attachment')) {
				alert_msg('稿件上传失败：' . $this->upload->display_errors('', ''));
			} else {
				$attachment_url = 'uploads/attachment/' . $this->upload->data('file_name');
			}

			//接收稿件其他信息
			$article = array(
				'user_id' => $this->session->userdata('user_id'),
				'title' => $this->input->post('title'),
				'keywords' => $this->input->post('keywords'),
				'abstract' => $this->input->post('abstract'),
				'attachment_url' => $attachment_url,
				'create_time' => time(),
				'author' => $this->input->post('author'),
			);

			//判断是修改稿件操作还是投稿操作，如果get过来正确得article_id为修改操作
			if (is_numeric($article_id)) {
				//获取该稿件得审核状态，防止通过get接收check_status时值被恶意篡改
				$old_article = $this->db->select('check_status, attachment_url')->get_where('article', array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id')))->result_array();

				//判断是否为该用户的投稿
				if (empty($old_article) || $old_article[0]['check_status'] > -2) {
					alert_msg('权限不足');
				}

				//执行修改操作
				$article['check_status'] = -2 - $old_article[0]['check_status']; //改为返修前的状态

				//如果是编委定稿时要求返修，不改变指定审核状态，还由原来的编委审核
				if ($article['check_status'] != 2) {
					$article['allot_status'] = 0;
				}

				$status = $this->db->update('article', $article, array('article_id' => $article_id));
				$message = '修改稿件';

				//短路与，修改成功后删除原来的上传的稿件附件
				$status && @unlink($this->config->item('MYPATH') . $old_article[0]['attachment_url']);
			} else {
				$status = $this->db->insert('article', $article);
				$message = '投稿';
			}

			//判断操作是否成功
			$status ? alert_msg('恭喜您' . $message . '成功，我们会尽快为您审核！', 'back2') : alert_msg($message . '失败，请稍后重试！');

		} else {
			$this->load->view('myhome/contribute.html');
		}
	}

	/*
		稿件列表
		check_status => 全部='all'	录用='use'	待审核='no'	被拒='refuses' 返修='edit'
	 */
	public function list_article($check_status, $offset = 0) {
		$this->load->library('myclass');

		//根据要浏览的稿件类型拼接where条件
		$view_html = 'author/list_article.html';
		switch ($check_status) {
		case 'wait_check': //未完成审核
			$where_arr = array('check_status !=' => '-1', 'check_status <' => 3);
			break;
		case 'use': //录用
			$where_arr = array('check_status' => '3');
			break;
		case 'refuses': //被拒稿件
			$where_arr = array('check_status' => '-1');
			break;
		case 'edit': //返修稿件
			$where_arr = array('check_status <' => '-1');
			$view_html = 'author/list_edit_article.html';
			break;
		}
		$where_arr['user_id'] = $this->session->userdata('user_id');
		$per_page = 10;
		$page_url = site_url('index/author/list_article/' . $check_status);
		$total_rows = $this->db->where($where_arr)->count_all_results('article');
		$offset_uri_segment = 5;
		//获取分页html
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);

		//获取文章列表信息
		$data['article'] = $this->index_model->get_list_article($where_arr, $offset, $per_page);

		$this->load->view($view_html, $data);
	}

	/*
		修改稿件
	 */
	public function article($article_id, $action = 'see') {
		if (!is_numeric($article_id)) {
			alert_msg('权限不足');
		}

		//判断是否为作者自己的稿件,并且稿件是否为返修状态
		$where_arr = array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id'));
		$article = $this->index_model->get_info_article($where_arr);
		if (empty($article)) {
			alert_msg('权限不足');
		}

		//进行修改的操作
		if ($action == 'edit') {
			//执行修改
			//
			//上传文件配置
			$config = array(
				'upload_path' => './uploads/attachment/',
				'allowed_types' => 'doc|docx|pdf|latex', //限制稿件格式，暂定
				'file_name' => time() . mt_rand(1000, 9999),
			);
			$this->load->library('upload', $config);
			$attachment_url = ''; //稿件上传地址
			//判断文件是否上传成功，否则返回错误提示信息
			if (!$this->upload->do_upload('attachment')) {
				alert_msg('稿件上传失败：' . $this->upload->display_errors('', ''));
			} else {
				$attachment_url = 'uploads/attachment/' . $this->upload->data('file_name');
			}

			//接收稿件其他信息
			$data = array(
				'title' => $this->input->post('title'),
				'keywords' => $this->input->post('keywords'),
				'abstract' => $this->input->post('abstract'),
				'attachment_url' => $attachment_url,
				'create_time' => time(),
				'author' => $this->input->post('author'),
				'check_status' => -$article[0]['check_status'] - 2,
				'allot_status' => 0,
			);
			$status = $this->db->update('article', $data, array('article_id' => $article_id));
			if ($status) {
				@unlink($this->config->item('MYPATH') . $article[0]['attachment_url']);
				alert_msg('修改成功！', 'back2');
			} else {
				alert_msg('修改失败，请稍后重试!');
			}
		} else {
			//查看要修改稿件的具体信息
			$data['article'] = $article[0];

			//审核意见

			//判断是浏览稿件还是修改稿件
			if ($action == 'see_edit' && $article[0]['check_status'] < -1) {
				$view_html = 'author/edit_article.html';
			} else {
				$view_html = 'author/info_article.html';
			}
			//获取审核意见
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id, 'suggest.status is not null' => null));
			$this->load->view($view_html, $data);
		}
	}
}