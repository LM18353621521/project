<?php

namespace app\mobile\controller;
use think\Db;
use app\common\model\WxNews;
class Article extends MobileBase
{
    /**
     * 文章内容页
     */
    public function detail()
    {
        $article_id = input('article_id/d', 1);
        $article = Db::name('article')->where("article_id", $article_id)->find();
        $this->assign('article', $article);
        return $this->fetch();
    }
    public function news()
    {
        $id = input('id');
        if (!$news = WxNews::get($id)) {
            $this->error('文章不存在了~', null, '', 100);
        }

        $news->content = htmlspecialchars_decode($news->content);
        $this->assign('news', $news);
        return $this->fetch();
    }

    //文章详情
    public function technical_doc(){
        $id = I('id');

        $where = array('article_id'=>$id);
        $news = M('article')->where($where)->find();

        if(!$news) $this->error('文章不存在了~', null, '', 5);

        $news['content'] = htmlspecialchars_decode( $news['content']);
        $this->assign('news',$news);
        return $this->fetch();
    }
}