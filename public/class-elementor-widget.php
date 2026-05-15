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

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            array(
                'label' => __( 'Stijl', 'struijck-agenda' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'primary_color',
            array(
                'label'     => __( 'Hoofdkleur', 'struijck-agenda' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .struijck-agenda' => '--sa-color-primary: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label'     => __( 'Accentkleur (vandaag)', 'struijck-agenda' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .struijck-agenda' => '--sa-color-accent: {{VALUE}}; --sa-color-today-border: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $shortcode = sprintf(
            '[struijck_agenda view="%s" zaal="%s" filters="%s"]',
            esc_attr( $settings['view'] ),
            esc_attr( $settings['zaal'] ),
            'yes' === $settings['filters'] ? 'yes' : 'no'
        );
        echo do_shortcode( $shortcode );
    }
}
