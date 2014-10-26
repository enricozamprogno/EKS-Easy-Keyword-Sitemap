<?php
/**
 *  @Copyright
 *  @package        EKS - Easy Keyword Sitemap
 *  @author         Viktor Vogel {@link http://www.kubik-rubik.de}
 *  @version        2.5-4 - 2013-07-01
 *  @link           Project Site {@link http://joomla-extensions.kubik-rubik.de/eks-easy-keyword-sitemap}
 *
 *  @license GNU/GPL
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('_JEXEC') or die('Restricted access');

/**
 * Class plgContentEasyKeywordSitemap
 *
 * Creates the semantic keyword sitemap
 */
class plgContentEasyKeywordSitemap extends JPlugin
{
    protected $_eks_parameters;

    function __construct(&$subject, $config)
    {
        // Do not execute in the administration
        $app = JFactory::getApplication();

        if($app->isAdmin())
        {
            return;
        }

        parent::__construct($subject, $config);
        $this->loadLanguage('', JPATH_ADMINISTRATOR);
    }

    /**
     * Easy Keyword Sitemap is triggered from the content component by the trigger onContentPrepare
     */
    function onContentPrepare($context, &$article, &$params, $limitstart)
    {
        if(!preg_match("@{eks}(.*){/eks}@isU", $article->text))
        {
            return;
        }

        if(preg_match_all("@{eks}(.*){/eks}@isU", $article->text, $matches, PREG_PATTERN_ORDER) > 0)
        {
            $count_match = 0;

            foreach($matches[1] as $match)
            {
                if(!empty($match))
                {
                    $this->_eks_parameters = array();

                    $eks_parameters_temp = explode('|', $match);

                    foreach($eks_parameters_temp as $eks_parameter_temp)
                    {
                        if(preg_match('@=@', $eks_parameter_temp))
                        {
                            $eks_parameter_temp = explode('=', $eks_parameter_temp);

                            if(preg_match('@,@', $eks_parameter_temp[1]))
                            {
                                $eks_parameter_temp[1] = array_map(array($this, 'mb_trim'), explode(',', $eks_parameter_temp[1]));
                            }

                            $this->_eks_parameters[$eks_parameter_temp[0]] = $eks_parameter_temp[1];
                        }
                        else
                        {
                            $this->_eks_parameters[$eks_parameter_temp] = true;
                        }
                    }

                    if(!empty($this->_eks_parameters['catid']))
                    {
                        $articles = $this->articlesData($this->_eks_parameters['catid']);
                    }
                    else
                    {
                        $articles = $this->articlesData();
                    }
                }
                else
                {
                    $articles = $this->articlesData();
                }

                if(!empty($articles))
                {
                    $output_data = $this->keywordsData($articles);
                }

                $html = '<!-- Easy Keyword Sitemap - Kubik-Rubik Joomla! Extensions by Viktor Vogel -->';
                $html .= '<div class="eks">';

                if(!empty($output_data))
                {
                    if(!empty($this->_eks_parameters['alpha']))
                    {
                        $alpha_index = $this->createAlphaIndex($output_data, $count_match);

                        $html .= $alpha_index[0];
                    }

                    foreach($output_data as $keyword => $output_values)
                    {
                        if(!empty($alpha_index[1]))
                        {
                            $keyword_first_char = $this->firstCharAlphaIndex($keyword);

                            if(in_array($keyword_first_char, $alpha_index[1]))
                            {
                                $html .= '<a id="eks_'.strtolower($keyword_first_char).'_'.$count_match.'"></a>';

                                $alpha_index_key = array_search($keyword_first_char, $alpha_index[1]);
                                unset($alpha_index[1][$alpha_index_key]);
                            }
                        }

                        $html .= '<h2>'.$keyword.'</h2>';
                        $html .= '<ul>';

                        foreach($output_values as $output_value)
                        {
                            $html .= '<li><a href="'.$output_value->link.'" title="'.$output_value->title.'">'.$output_value->title.'</a>';

                            if(!empty($this->_eks_parameters['teaser']) AND !empty($output_value->metadesc))
                            {
                                $html .= '<br /><span class="eks_teaser">'.$output_value->metadesc.'</span>';
                            }

                            $html .= '</li>';
                        }

                        $html .= '</ul>';
                    }
                }
                else
                {
                    $html .= '<h2>Easy Keyword Sitemap</h2>';
                    $html .= '<p>'.JTEXT::_('PLG_EASYKEYWORDSITEMAP_NOARTICLLESFOUND').'</p>';
                }

                $html .= '</div>';

                $article->text = preg_replace("@(<p>)?{eks}".preg_quote($match)."{/eks}(</p>)?@is", $html, $article->text);

                $count_match++;
            }

            $css = '.eks {margin: 20px 0;}'."\n";
            $css .= '.eks_alphaindex {text-align: center;}'."\n";
            $css .= '.eks_teaser {font-size: 90%; font-style: italic;}';

            JFactory::getDocument()->addStyleDeclaration($css);
        }
    }

    /**
     * Helping function articlesData to load all articles with the set restrictions
     *
     * @param array $catids - category ids which should be included in the list
     * @return array $articles - return all articles which pass the restrictions
     */
    private function articlesData($catids = array())
    {
        $model = JModel::getInstance('Articles', 'ContentModel', array('ignore_request' => true));
        $app = JFactory::getApplication();
        $app_params = $app->getParams();
        $access = !JComponentHelper::getParams('com_content')->get('show_noauth');
        $authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));

        $model->setState('list.start', 0);
        $model->setState('filter.published', 1);
        $model->setState('filter.access', $access);
        $model->setState('filter.category_id', $catids);
        $model->setState('filter.language', $app->getLanguageFilter());
        $model->setState('list.ordering', 'a.id');
        $model->setState('list.direction', 'ASC');
        $model->setState('params', $app_params);

        $articles = $model->getItems();

        foreach($articles as &$article)
        {
            $article->slug = $article->id.':'.$article->alias;
            $article->catslug = $article->catid.':'.$article->category_alias;

            if($access OR in_array($article->access, $authorised))
            {
                $article->link = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug));
            }
            else
            {
                $article->link = JRoute::_('index.php?option=com_users&view=login');
            }
        }

        return $articles;
    }

    /**
     * Get the keywords list with all keywords which are allowed
     *
     * @param array $articles - Array of possible articles
     * @return array $keyword_list - List of allowed keywords
     */
    private function keywordsData($articles)
    {
        $keywords_list = array();

        foreach($articles as $article)
        {
            if(!empty($article->metakey))
            {
                $metakey_array = array_map('trim', explode(',', $article->metakey));

                foreach($metakey_array as $metakey)
                {
                    $keywords_list[ucfirst($metakey)][] = $article;
                }
            }
        }

        ksort($keywords_list);

        if(!empty($this->_eks_parameters['keyword']))
        {
            foreach($keywords_list as $key => $value)
            {
                if(is_array($this->_eks_parameters['keyword']))
                {
                    if(!in_array(strtolower($key), strtolower($this->_eks_parameters['keyword'])))
                    {
                        unset($keywords_list[$key]);
                        continue;
                    }
                }
                else
                {
                    if(strtolower($key) != strtolower($this->_eks_parameters['keyword']))
                    {
                        unset($keywords_list[$key]);
                        continue;
                    }
                }
            }
        }
        elseif(!empty($this->_eks_parameters['nokeyword']))
        {
            foreach($keywords_list as $key => $value)
            {
                if(is_array($this->_eks_parameters['nokeyword']))
                {
                    if(in_array(strtolower($key), $this->_eks_parameters['nokeyword']))
                    {
                        unset($keywords_list[$key]);
                        continue;
                    }
                }
                else
                {
                    if(strtolower($key) == strtolower($this->_eks_parameters['nokeyword']))
                    {
                        unset($keywords_list[$key]);
                        continue;
                    }
                }
            }
        }

        return $keywords_list;
    }

    /**
     * Function creates the alpha index from all selected keywords
     *
     * @param array $data_array - Keywords list array with all articles which are included in the output
     * @param int $count_match - Number for the IDs which have to be unique
     * @return array - HTML output of the alpha index and all first letters of allowed keywords for the creation of the anchors
     */
    private function createAlphaIndex($data_array, $count_match)
    {
        $data_keys_array = array_unique(array_map(array($this, 'firstCharAlphaIndex'), array_keys($data_array)));
        $data_range = range('A', 'Z');

        $html = '<div class="eks_alphaindex">';

        foreach($data_range as $value)
        {
            if(in_array($value, $data_keys_array))
            {
                $html .= '<a href="#eks_'.strtolower($value).'_'.$count_match.'">'.$value.'</a> ';
            }
            else
            {
                $html .= $value.' ';
            }
        }

        $html .= '</div>';

        return array($html, $data_keys_array);
    }

    /**
     * Small helper function to get the first character of the
     * passed string which is needed to create the alpha index
     *
     * @param string $value
     * @return string - First character of the passed string
     */
    private function firstCharAlphaIndex($value)
    {
        return substr($value, 0, 1);
    }

    /**
     * Small helper function to trim UTF-8 encoded strings
     *
     * @param string $string - UTF-8 encoded string
     * @return string - Trimmed UTF-8 encoded string
     */
    private function mb_trim($utf8string)
    {
        return preg_replace('@(^\s+)|(\s+$)@su', '', $utf8string);
    }

}
