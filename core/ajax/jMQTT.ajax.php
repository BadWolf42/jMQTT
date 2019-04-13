<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - {{Accès non autorisé}}', __FILE__));
    }

    ajax::init();

    // To change the equipment automatic inclusion mode
    if (init('action') == 'changeIncludeMode') {
        $broker = jMQTT::getBrokerFromId(init('id'));
        $broker->changeIncludeMode(init('mode'));
        ajax::success();
    }

    if (init('action') == 'getDaemonInfo') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->getDaemonInfo());
    }
    
    if (init('action') == 'getDaemonState') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->getDaemonState());
    }
    
    if (init('action') == 'daemonStart') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->startDaemon(true, true));
    }
    
    if (init('action') == 'daemonStop') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->stopDaemon());
    }
    
    if (init('action') == 'daemonChangeAutoMode') {
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Accès non autorisé', __FILE__));
        }
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->setDaemonAutoMode(init('mode')));
    }
    
    if (init('action') == 'dev') {
//         foreach (eqLogic::byType('jMQTT') as $eqL) {
//             $eqL->setConfiguration('type', null);
//             $eqL->save();
//         }
//         require_once __DIR__ . '/../../plugin_info/install.php';
//         ob_start();
//         jMQTT_update();
//         ob_end_clean();

        foreach (eqLogic::byType('jMQTT') as $eql) {
            /** @var jMQTT $eql */
            $eql->setConfiguration('prev_Qos', null);
            $eql->setConfiguration('prev_isActive', null);
            $eql->setConfiguration('reload_d', null);
            $eql->setConfiguration('prev_mqttId', null);
            $eql->save(true);
        }
        
        
        ajax::success();
    }
    
    
    throw new Exception(__('Aucune methode Ajax correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
