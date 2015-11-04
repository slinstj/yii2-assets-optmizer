<?php
namespace slinstj\AssetsOptimizer;

use MatthiasMullie\Minify;

/**
 * A modified View class capable of optimizing (minify and combine) assets bundles.
 * @author Sidney Lins (slinstj@gmail.com)
 */
class View extends \yii\web\View
{

    /** @var bool */
    public $minify = true;

    /** @var bool */
    public $combine = true;

    /**
     * @var string Path where optimized css file will be published in. If you change this,
     * you *must* change [[optmizedCssPath]] accordingly.
     * Optional. Defaults to '@webroot/yao'.
     */
    public $optimizedCssPath = '@webroot/yao';

    /**
     * @var string Web acessible Url where optimized css file(s) will be published in. 
     * *Must* be in according to [[optmizedCssPath]].
     * Optional. Defaults to '@web/yao'.
     */
    public $optimizedCssUrl = '@web/yao';

    /**
     * @inheritdoc
     */
    public function endPage($ajaxMode = false)
    {
        $this->trigger(self::EVENT_END_PAGE);

        $content = ob_get_clean();

        if ($this->minify === true) {
            $this->optimizeCss();
        }

        echo strtr(
            $content,
            [
                self::PH_HEAD => $this->renderHeadHtml(),
                self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
                self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
            ]
        );

        $this->clear();
    }

    /**
     * @return self
     */
    protected function optimizeCss()
    {
        $result = $this->minifyFiles(array_keys($this->cssFiles), 'css');
        $this->saveOptimizedCssFile($result);
    }

    protected function minifyFiles($fileUrls, $type)
    {
        $min = ($type = strtolower($type)) === 'css' ? new Minify\CSS() : new Minify\JS;
        foreach ($fileUrls as $filePath) {
            $resolvedPath = $this->resolvePath($filePath);
            $min->add($resolvedPath);
            if($type === 'css') {
                unset($this->cssFiles[$filePath]);
            } else {
                unset($this->jsFiles[$filePath]);
            }
        }
        return $min->minify();
    }

    protected function resolvePath($path)
    {
        $basePath = \Yii::getAlias('@webroot');
        $baseUrl = str_replace(\Yii::getAlias('@web'), '', $path);
        $resolvedPath = realpath($basePath . DIRECTORY_SEPARATOR . $baseUrl);
        return $resolvedPath;
    }

    protected function saveOptimizedCssFile($content)
    {
        $finalPath = $this->saveFile($content, \Yii::getAlias($this->optimizedCssPath), 'css');
        $finalUrl = \Yii::getAlias($this->optimizedCssUrl) . DIRECTORY_SEPARATOR . basename($finalPath);

        $this->cssFiles[$finalPath] = \yii\helpers\Html::cssFile($finalUrl);
    }

    protected function saveFile($content, $filePath, $ext)
    {
        $filename = sha1($content) . '.' . $ext;
        \yii\helpers\FileHelper::createDirectory($filePath);
        $finalPath = $filePath . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($finalPath, $content, LOCK_EX) !== false) {
            return $finalPath;
        } else {
            throw new \Exception("Was not possible to save the file '$finalPath'.");
        }

    }

    protected function isValidPath($path)
    {
        return !empty($path) && realpath(($realPath = \Yii::getAlias($path)));
    }
}