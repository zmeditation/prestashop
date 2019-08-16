<?php
/**
 *  @author Clerk.io
 *  @copyright Copyright (c) 2017 Clerk.io
 *
 *  @license MIT License
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */


abstract class ClerkAbstractFrontController extends ModuleFrontController
{
    protected $logger;
    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var string
     */
    protected $order;

    /**
     * @var string
     */
    protected $order_by;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var array
     */
    protected $fieldHandlers = array();

    /**
     * @var array
     */
    protected $fieldMap = array();

    /**
     * ClerkAbstractFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
        require_once (_PS_MODULE_DIR_. $this->module->name . '/controllers/admin/ClerkLogger.php');
        $this->logger = new ClerkLogger();
    }

    /**
     * Display output
     */
    public function displayAjax()
    {
        try {

            header('Content-type: application/json;charset=utf-8');

            if (!$this->validateRequest()) {
                $this->jsonUnauthorized();
            }

            $this->getArguments();

            $response = $this->getJsonResponse();

            $this->ajaxDie(Tools::jsonEncode($response));

        } catch (Exception $e) {

            $this->logger->error('ERROR displayAjax', ['error' => $e]);

        }
    }

    /**
     * Validate request
     *
     * @param $request
     * @return bool
     */
    protected function validateRequest()
    {
        try {

            $this->logger->warn('Validating API keys Started', []);
            $public_key = Tools::getValue('key', '');
            $private_key = Tools::getValue('private_key', '');

            if ($public_key === Configuration::get('CLERK_PUBLIC_KEY', $this->getLanguageId(), null, $this->getShopId()) && $private_key === Configuration::get('CLERK_PRIVATE_KEY', $this->getLanguageId(), null, $this->getShopId())) {

                $this->logger->log('API key was validated', ['response' => true]);
                return true;

            }

            $this->logger->warn('API keys was not validated', ['response' => false]);
            return false;

        } catch (Exception $e) {

            $this->logger->error('ERROR validateRequest', ['error' => $e]);

        }
    }

    /**
     * Display unauthorized response
     */
    public function jsonUnauthorized()
    {

        try {

            header('HTTP/1.1 403');

            $response = array(
                'code' => 403,
                'message' => 'Invalid keys supplied',
                'description' => $this->module->l('The supplied public or private key is invalid'),
                'how_to_fix' => $this->module->l('Ensure that the proper keys are set up in the configuration'),
            );

            $this->logger->warn('Invalid API keys supplied', ['response' => $response]);
            $this->ajaxDie(Tools::jsonEncode($response));

            return;

        } catch (Exception $e) {

            $this->logger->error('ERROR jsonUnauthorized', ['error' => $e]);

        }
    }

    /**
     * Get mapped field name
     *
     * @param $field
     *
     * @return mixed
     */
    protected function getFieldName($field)
    {
        try {

            if (isset($this->fieldMap[$field])) {
                return $this->fieldMap[$field];
            }
            $this->logger->log('Fetched file name', ['response' => $field]);
            return $field;

        } catch (Exception $e) {

            $this->logger->error('ERROR getFieldName', ['error' => $e]);

        }
    }

    /**
     * Parse request arguments
     */
    protected function getArguments()
    {
        try {

            $this->debug = (bool)Tools::getValue('debug', false);
            $this->limit = (int)Tools::getValue('limit', 0);
            $this->page = (int)Tools::getValue('page', 0);
            $this->order_by = Tools::getValue('orderby', 'id_product');
            $this->order = Tools::getValue('order', 'desc');

            $this->offset = 0;

            if ($this->page > 0) {
                $this->offset = $this->page * $this->limit;
            }

            /**
             * Explode fields on , and filter out "empty" entries
             */
            $fields = (string)Tools::getValue('fields');
            if ($fields) {
                $this->fields = array_filter(explode(',', $fields), 'strlen');
            } else {
                $this->fields = $this->getDefaultFields();
            }
            $this->fields = array_merge(array('id'), $this->fields);

            $this->logger->log('Arguments are now set', ['response' => '']);

        } catch (Exception $e) {

            $this->logger->error('ERROR getArguments', ['error' => $e]);

        }
    }

    /**
     * Add fieldhandler
     *
     * @param $field
     *
     * @param callable $handler
     */
    protected function addFieldHandler($field, $handler)
    {
        try {

        $this->fieldHandlers[$field] = $handler;

        } catch (Exception $e) {

            $this->logger->error('ERROR addFieldHandler', ['error' => $e]);

        }
    }

    /**
     * Get default fields
     *
     * @return array
     */
    protected function getDefaultFields()
    {
        return array();
    }

    /**
     * Dies and echoes output value
     *
     * @param string|null $value
     * @param string|null $controller
     * @param string|null $method
     * @return
     */
    protected function ajaxDie($value = null, $controller = null, $method = null)
    {
        try {

            //Call parent ajaxDie if available
            if (is_callable('parent::ajaxDie')) {
                return parent::ajaxDie($value, $controller, $method);
            }

            //Replicate functionality if not
            if ($controller === null) {
                $controller = get_class($this);
            }

            if ($method === null) {
                $bt = debug_backtrace();
                $method = $bt[1]['function'];
            }

            Hook::exec('actionBeforeAjaxDie', array('controller' => $controller, 'method' => $method, 'value' => $value));
            Hook::exec('actionBeforeAjaxDie' . $controller . $method, array('value' => $value));

            $this->logger->log('AJAX Killed', ['response' => '']);

            die($value);

        } catch (Exception $e) {

            $this->logger->error('ERROR ajaxDie', ['error' => $e]);

        }

    }

    /**
     * Get language id
     *
     * @return int
     */
    protected function getLanguageId()
    {

        $this->logger->log('Fetched language id', ['response' => '']);
        return $this->context->language->id;

    }

    /**
     * Get shop id
     *
     * @return int
     */
    protected function getShopId()
    {
        $this->logger->log('Fetched shop id', ['response' => '']);
        return $this->context->shop->id;
    }
}
