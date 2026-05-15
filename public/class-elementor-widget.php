<?php
/**
 * Elementor widget voor Struijck Agenda.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Struijck_Agenda_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'struijck_agenda';
    }

    public function get_title() {
        return __( 'Struijck Agenda', 'struijck-agenda' );
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'agenda', 'kalender', 'calendar', 'events', 'struijck', 'sporthal' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __( 'Agenda', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'view',
            array(
                'label'   => __( 'Standaard weergave', 'struijck-agenda' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'month',
                'options' => array(
                    'month' => __( 'Maand', 'struijck-agenda' ),
                    'week'  => __( 'Week', 'struijck-agenda' ),
                    'list'  => __( 'Lijst', 'struijck-agenda' ),
                ),
            )
        );

        // Build zalen options.
        $zalen_options = array( '' => __( 'Alle zalen', 'struijck-agenda' ) );
        $terms         = get_terms( array( 'taxonomy' => 'struijck_zaal', 'hide_empty' => false ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $zalen_options[ $term->slug ] = $term->name;
            }
        }

        $this->add_control(
            'zaal',
            array(
                'label'   => __( 'Beperken tot zaal', 'struijck-agenda' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => $zalen_options,
            )
        );

        $this->add_control(
            'filters',
            array(
                'label'        => __( 'Toon zaal-filter', 'struijck-agenda' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Ja', 'struijck-agenda' ),
                'label_off'    => __( 'Nee', 'struijck-agenda' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'requests',
            array(
                'label'        => __( 'Aanvragen toestaan', 'struijck-agenda' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Ja', 'struijck-agenda' ),
                'label_off'    => __( 'Nee', 'struijck-agenda' ),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __( 'Toont een knop waarmee bezoekers een datum/tijd kunnen aanvragen.', 'struijck-agenda' ),
            )
        );

        $this->end_controls_section();

        /* ===== Style: Kleuren ===== */
        $this->start_controls_section(
            'style_colors',
            array(
                'label' => __( 'Kleuren', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $colors = array(
            'c_text'      => array( __( 'Tekstkleur', 'struijck-agenda' ), '--sa-text' ),
            'c_text_soft' => array( __( 'Zachte tekst (subtitels)', 'struijck-agenda' ), '--sa-text-soft' ),
            'c_accent'    => array( __( 'Accentkleur', 'struijck-agenda' ), '--sa-accent' ),
            'c_bg'        => array( __( 'Achtergrond (kaart)', 'struijck-agenda' ), '--sa-bg' ),
            'c_bg_soft'   => array( __( 'Zachte achtergrond', 'struijck-agenda' ), '--sa-bg-soft' ),
            'c_border'    => array( __( 'Randkleur', 'struijck-agenda' ), '--sa-border' ),
            'c_time'      => array( __( 'Tijd-kleur', 'struijck-agenda' ), '--sa-time-color' ),
        );
        foreach ( $colors as $key => $data ) {
            $this->add_control(
                $key,
                array(
                    'label'     => $data[0],
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => array(
                        '{{WRAPPER}} .struijck-agenda' => $data[1] . ': {{VALUE}};',
                    ),
                )
            );
        }

        $this->end_controls_section();

        /* ===== Style: Vormgeving ===== */
        $this->start_controls_section(
            'style_shape',
            array(
                'label' => __( 'Vormgeving', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'radius',
            array(
                'label'      => __( 'Hoekafronding (kaart)', 'struijck-agenda' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .struijck-agenda' => '--sa-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'radius_sm',
            array(
                'label'      => __( 'Kleine afronding (knoppen/pills)', 'struijck-agenda' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .struijck-agenda' => '--sa-radius-sm: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'content_padding',
            array(
                'label'      => __( 'Binnenmarge dag-inhoud', 'struijck-agenda' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', 'rem' ),
                'selectors'  => array(
                    '{{WRAPPER}} .struijck-agenda .sa-day-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        /* ===== Style: Typografie ===== */
        $this->start_controls_section(
            'style_typography',
            array(
                'label' => __( 'Typografie', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'typo_eyebrow',
                'label'    => __( 'Eyebrow (label boven titel)', 'struijck-agenda' ),
                'selector' => '{{WRAPPER}} .struijck-agenda .sa-week-eyebrow',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'typo_time',
                'label'    => __( 'Tijden', 'struijck-agenda' ),
                'selector' => '{{WRAPPER}} .struijck-agenda .sa-day-row__time',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'typo_title',
                'label'    => __( 'Boekingstitel', 'struijck-agenda' ),
                'selector' => '{{WRAPPER}} .struijck-agenda .sa-day-row__title',
            )
        );

        $this->end_controls_section();

        /* ===== Style: Aanvraagformulier ===== */
        $this->start_controls_section(
            'style_request',
            array(
                'label' => __( 'Aanvraagformulier', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'request_btn_color',
            array(
                'label'     => __( 'Aanvraag-knop kleur', 'struijck-agenda' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .struijck-agenda' => '--sa-request-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'typo_form_label',
                'label'    => __( 'Formulier-labels', 'struijck-agenda' ),
                'selector' => '{{WRAPPER}} .struijck-agenda .sa-field > span',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $shortcode = sprintf(
            '[struijck_agenda view="%s" zaal="%s" filters="%s" requests="%s"]',
            esc_attr( $settings['view'] ),
            esc_attr( $settings['zaal'] ),
            'yes' === $settings['filters'] ? 'yes' : 'no',
            ( isset( $settings['requests'] ) && 'yes' === $settings['requests'] ) ? 'yes' : 'no'
        );
        echo do_shortcode( $shortcode );
    }
}
